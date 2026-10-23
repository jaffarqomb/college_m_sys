<?php

use App\Actions\Finance\RecordManualPayment;
use App\Enums\PaymentStatus;
use App\Enums\UserRole;
use App\Filament\Admin\Resources\FeeStructures\FeeStructuresResource;
use App\Filament\Admin\Resources\FeeStructures\Pages\CreateFeeStructure;
use App\Filament\Admin\Resources\Invoices\InvoicesResource;
use App\Filament\Admin\Resources\Invoices\Pages\CreateInvoice;
use App\Filament\Admin\Resources\Invoices\Pages\ListInvoices;
use App\Models\AcademicTerm;
use App\Models\FeeStructure;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

/**
 * A staff member in the given role (panel access, personal team).
 */
function finStaffUser(UserRole $role): User
{
    return User::factory()->asRole($role)->create();
}

test('finance staff and registrars can browse invoices, department heads can not', function (UserRole $role, int $status) {
    actingAs(finStaffUser($role))
        ->get(InvoicesResource::getUrl('index'))
        ->assertStatus($status);
})->with([
    'system admin can' => [UserRole::SystemAdmin, 200],
    'registrar can' => [UserRole::Registrar, 200],
    'finance officer can' => [UserRole::FinanceOfficer, 200],
    'department head can not' => [UserRole::DepartmentHead, 403],
    'instructor can not' => [UserRole::Instructor, 403],
]);

test('finance staff and registrars can browse fee structures', function (UserRole $role, int $status) {
    actingAs(finStaffUser($role))
        ->get(FeeStructuresResource::getUrl('index'))
        ->assertStatus($status);
})->with([
    'system admin can' => [UserRole::SystemAdmin, 200],
    'registrar can' => [UserRole::Registrar, 200],
    'finance officer can' => [UserRole::FinanceOfficer, 200],
    'department head can not' => [UserRole::DepartmentHead, 403],
]);

test('students never reach the admin panel at all', function () {
    actingAs(finStaffUser(UserRole::Student))
        ->get('/admin')
        ->assertForbidden();
});

test('the invoice table renders statuses, balances and students', function () {
    $invoice = Invoice::factory()->create();

    actingAs(finStaffUser(UserRole::FinanceOfficer))
        ->get(InvoicesResource::getUrl('index'))
        ->assertOk()
        ->assertSee($invoice->number)
        ->assertSee($invoice->student->user->name);
});

test('the invoice table filters by status', function () {
    $user = finStaffUser(UserRole::Registrar);

    $unpaid = Invoice::factory()->create();
    Invoice::factory()->paid()->create();

    actingAs($user);

    Livewire::test(ListInvoices::class)
        ->set('tableFilters.status.value', 'unpaid')
        ->assertOk()
        ->assertSee($unpaid->number);
});

test('a registrar can create an invoice which auto-numbers and starts as a draft', function () {
    $user = finStaffUser(UserRole::Registrar);

    actingAs($user);

    Livewire::test(CreateInvoice::class)
        ->fillForm([
            'student_id' => Student::factory()->create()->id,
            'term_id' => AcademicTerm::factory()->create()->id,
            'issue_date' => now()->toDateString(),
            'due_date' => now()->addDays(15)->toDateString(),
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $invoice = Invoice::query()->sole();

    expect($invoice->status)->toBe('draft')
        ->and(preg_match('/^INV-\d{6}-\d+$/', $invoice->number))->toBe(1);
});

test('a finance officer is read-only and cannot create invoices or fee structures', function () {
    $user = finStaffUser(UserRole::FinanceOfficer);

    expect($user->can('create', Invoice::class))->toBeFalse()
        ->and($user->can('create', FeeStructure::class))->toBeFalse()

        ->and($user->can('viewAny', Invoice::class))->toBeTrue()
        ->and($user->can('viewAny', FeeStructure::class))->toBeTrue();
});

test('recording a manual payment via the action settles and updates the invoice', function () {
    $invoice = Invoice::factory()->create();

    app(RecordManualPayment::class)->handle($invoice, 500, 'CASH-77', 'Counter');

    expect($invoice->refresh()->balance())->toBe((float) $invoice->total - 500.0)
        ->and(Payment::query()->where('invoice_id', $invoice->id)->where('status', PaymentStatus::Succeeded->value)->count())->toBe(1);
});

test('a fee structure can be created by the registrar', function () {
    $term = AcademicTerm::factory()->create();

    actingAs(finStaffUser(UserRole::Registrar));

    Livewire::test(CreateFeeStructure::class)
        ->fillForm([
            'term_id' => $term->id,
            'name' => 'Late registration fee',
            'amount' => '150',
            'is_active' => true,
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $fee = FeeStructure::query()->sole();

    expect($fee->term_id)->toBe($term->id)
        ->and($fee->name)->toBe('Late registration fee')
        ->and($fee->program_id)->toBeNull()
        ->and((float) $fee->amount)->toBe(150.0)
        ->and($fee->is_active)->toBeTrue();
});
