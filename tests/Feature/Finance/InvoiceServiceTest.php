<?php

use App\Actions\Finance\GenerateTermInvoices;
use App\Actions\Finance\PublishInvoice;
use App\Enums\InvoiceStatus;
use App\Enums\PaymentMethod;
use App\Models\AcademicTerm;
use App\Models\CourseSection;
use App\Models\Enrollment;
use App\Models\FeeStructure;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Program;
use App\Models\Student;
use App\Services\Finance\InvoiceService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * A term with two programs, enrolled students and fee structures:
 * enrollment returns the STUDENTS keyed for assertions.
 *
 * @return array{term: AcademicTerm, programs: array<string, Program>, students: array<string, Student>, service: InvoiceService}
 */
function invSetup(): array
{
    $term = AcademicTerm::factory()->create();

    $programs = [
        'cs' => Program::factory()->create(),
        'ds' => Program::factory()->create(),
    ];

    $section = CourseSection::factory()->create(['term_id' => $term->id]);

    $students = [];
    foreach ($programs as $key => $program) {
        $student = Student::factory()->create(['program_id' => $program->id]);
        Enrollment::factory()->create([
            'student_id' => $student->id,
            'section_id' => $section->id,
        ]);
        $students[$key] = $student;
    }

    FeeStructure::query()->create(['term_id' => $term->id, 'program_id' => null, 'name' => 'Registration', 'amount' => 250, 'is_active' => true]);
    FeeStructure::query()->create(['term_id' => $term->id, 'program_id' => $programs['cs']->id, 'name' => 'CS tuition', 'amount' => 1000, 'is_active' => true]);
    FeeStructure::query()->create(['term_id' => $term->id, 'program_id' => $programs['ds']->id, 'name' => 'DS tuition', 'amount' => 900, 'is_active' => false]);

    return [
        'term' => $term,
        'programs' => $programs,
        'students' => $students,
        'service' => new InvoiceService,
    ];
}

test('generating term invoices creates one draft per enrolled student with scoped fee items', function () {
    ['term' => $term, 'service' => $service] = invSetup();

    $created = $service->generateForTerm($term);

    expect(count($created))->toBe(2)
        ->and($service->generateForTerm($term))->toBe([])->and(Invoice::query()->where('term_id', $term->id)->count())->toBe(2);

    $invoices = Invoice::query()->where('term_id', $term->id)->get()->sortBy('student_id')->values();

    foreach ($invoices as $invoice) {
        expect($invoice->status)->toBe(InvoiceStatus::Draft->value);
    }

    // The CS student is billed the program tuition plus the shared fee; the
    // DS program's tuition is inactive so only the shared fee applies.
    expect($invoices[0]->items()->pluck('description')->sort()->values()->all())->toBe(['CS tuition', 'Registration'])
        ->and($invoices[1]->items()->pluck('description')->values()->all())->toBe(['Registration']);
});

test('the billing run action throws when nothing is generated', function () {
    ['term' => $term] = invSetup();

    // First run generates.
    app(GenerateTermInvoices::class)->handle($term);

    // A second run is a no-op and surfaces as a validation error to the UI.
    app(GenerateTermInvoices::class)->handle($term);
})->throws('No invoices were generated. Add active fee structures for this term first.');

test('invoice totals equal the sum of their line items and numbers are unique', function () {
    ['term' => $term, 'service' => $service] = invSetup();

    $service->generateForTerm($term);

    $invoices = Invoice::query()->where('term_id', $term->id)->get();

    foreach ($invoices as $invoice) {
        expect((float) $invoice->total)->toBe((float) $invoice->items()->sum('amount'));
    }

    expect($invoices->pluck('number')->unique()->count())->toBe($invoices->count())
        ->and(preg_match('/^INV-\d{6}-\d+$/', $invoices->first()->number))->toBe(1);
});

test('publishing releases a draft and leaves non-drafts untouched', function () {
    ['service' => $service] = invSetup();

    $draft = Invoice::factory()->state(fn () => ['status' => 'draft'])->create();
    $published = Invoice::factory()->create();

    $service->publish($draft);

    expect($draft->refresh()->status)->toBe(InvoiceStatus::Unpaid->value)
        ->and($published->status)->toBe(InvoiceStatus::Unpaid->value);
});

test('publish action refuses non-draft invoices', function () {
    invSetup();

    app(PublishInvoice::class)->handle(Invoice::factory()->create());
})->throws('Only draft invoices can be published.');

test('recompute marks paid invoices fully settled and reflects partial payments', function () {
    ['term' => $term, 'service' => $service, 'students' => $students] = invSetup();

    $invoice = Invoice::factory()->state(fn () => ['term_id' => $term->id, 'total' => 1000])->create();

    // No payments yet → stays unpaid while still inside the due window.
    expect($service->recomputeStatus($invoice)->status)->toBe(InvoiceStatus::Unpaid->value)->toBe($invoice->refresh()->status);

    Payment::query()->create([
        'invoice_id' => $invoice->id,
        'amount' => 400,
        'method' => PaymentMethod::Manual->value,
        'status' => 'succeeded',
        'paid_at' => now(),
    ]);

    $service->recomputeStatus($invoice->refresh());

    expect($invoice->status)->toBe(InvoiceStatus::Partial->value);

    Payment::query()->create([
        'invoice_id' => $invoice->id,
        'amount' => 600,
        'method' => PaymentMethod::Tap->value,
        'status' => 'succeeded',
        'paid_at' => now(),
    ]);

    $service->recomputeStatus($invoice->refresh());

    expect($invoice->status)->toBe(InvoiceStatus::Paid->value)
        ->and($invoice->balance())->toBe(0.0);
});

test('recompute flags live invoices past their due date as overdue', function () {
    ['service' => $service] = invSetup();

    $lapsed = Invoice::factory()->state(fn () => ['due_date' => now()->subDays(5)])->create();
    $settledPast = Invoice::factory()->state(fn () => ['due_date' => now()->subDays(5)])->paid()->create();

    $service->recomputeStatus($lapsed->refresh());

    expect($lapsed->refresh()->status)->toBe(InvoiceStatus::Overdue->value)
        ->and($settledPast->status)->toBe(InvoiceStatus::Paid->value);
});

test('markOverdue sweeps unpaid and partially paid invoices only', function () {
    ['service' => $service] = invSetup();

    $unpaid = Invoice::factory()->state(fn () => ['due_date' => now()->subDays(2), 'status' => 'unpaid'])->create();
    $partial = Invoice::factory()->state(fn () => ['due_date' => now()->subDay(), 'status' => 'partial', 'total' => 100])->create();
    Invoice::factory()->state(fn () => ['due_date' => now()->subDays(2), 'status' => 'draft'])->create();

    $swept = $service->markOverdue();

    expect($swept)->toBe(2)
        ->and($unpaid->refresh()->status)->toBe(InvoiceStatus::Overdue->value)
        ->and($partial->refresh()->status)->toBe(InvoiceStatus::Overdue->value)
        ->and(Invoice::query()->where('status', InvoiceStatus::Draft->value)->count())->toBe(1);
});
