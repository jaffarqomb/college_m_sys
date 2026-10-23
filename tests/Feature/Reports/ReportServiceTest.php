<?php

use App\Actions\Academic\MarkAttendance;
use App\Actions\Academic\SaveSectionScores;
use App\Enums\AttendanceStatus;
use App\Models\AcademicTerm;
use App\Models\Assessment;
use App\Models\CourseSection;
use App\Models\Enrollment;
use App\Models\Invoice;
use App\Models\Payment;
use App\Services\Reports\ReportService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * @return array{0: AcademicTerm, 1: CourseSection, 2: Enrollment}
 */
function reportTermSectionEnrollment(): array
{
    $term = AcademicTerm::factory()->current()->create();

    $section = CourseSection::factory()->create([
        'term_id' => $term->id,
        'status' => 'open',
    ]);

    $enrollment = Enrollment::factory()->create(['section_id' => $section->id]);

    return [$term, $section, $enrollment];
}

test('the enrollments report lists every enrollment of the term', function () {
    [$term, $section, $enrollment] = reportTermSectionEnrollment();

    $csv = app(ReportService::class)->enrollmentsReport($term);

    $lines = explode("\n", trim($csv));

    expect($lines)->toHaveLength(2)
        ->and($lines[0])->toContain('Section', 'Student number', 'Status')
        ->and($lines[1])->toContain($section->fullCode(), $enrollment->student->user->name, 'active');
});

test('the invoices report includes per-invoice status and balance', function () {
    $term = AcademicTerm::factory()->current()->create();

    Invoice::factory()->create(['term_id' => $term->id, 'total' => 100, 'number' => 'INV-T-001']);

    $paidInvoice = Invoice::factory()->paid()->create(['term_id' => $term->id, 'total' => 200, 'number' => 'INV-T-002']);
    Payment::factory()->succeeded()->create([
        'invoice_id' => $paidInvoice->id,
        'amount' => 200,
    ]);

    $csv = app(ReportService::class)->invoicesReport($term);

    $lines = explode("\n", trim($csv));

    expect($lines)->toHaveLength(3)
        ->and($lines[0])->toContain('Invoice', 'Balance', 'Due date');

    $unpaid = str_getcsv($lines[1], ',', '"', '\\');
    $paid = str_getcsv($lines[2], ',', '"', '\\');

    expect($unpaid[3])->toBe('unpaid')
        ->and($unpaid[5])->toBe('0')
        ->and($unpaid[6])->toBe('100')
        ->and($paid[3])->toBe('paid')
        ->and($paid[5])->toBe('200')
        ->and($paid[6])->toBe('0');
});

test('the payments report lists succeeded payments only', function () {
    $term = AcademicTerm::factory()->current()->create();

    $invoice = Invoice::factory()->create(['term_id' => $term->id]);
    Payment::factory()->succeeded()->create(['invoice_id' => $invoice->id, 'reference' => 'CASH-9']);
    Payment::factory()->failed()->create(['invoice_id' => $invoice->id, 'reference' => 'CASH-BAD']);

    $csv = app(ReportService::class)->paymentsReport($term);

    $lines = explode("\n", trim($csv));

    expect($lines)->toHaveLength(2)
        ->and($lines[0])->toContain('Invoice', 'Method', 'Reference')
        ->and($lines[1])->toContain($invoice->number, 'manual', 'CASH-9')
        ->and($csv)->not->toContain('CASH-BAD');
});

test('the attendance report summarizes per-student held session counts', function () {
    [$term, $section, $enrollment] = reportTermSectionEnrollment();

    app(MarkAttendance::class)->handle($section, '2026-10-01', [
        $enrollment->id => AttendanceStatus::Present,
    ]);
    app(MarkAttendance::class)->handle($section, '2026-10-02', [
        $enrollment->id => AttendanceStatus::Absent,
    ]);

    $csv = app(ReportService::class)->attendanceReport($section);

    $lines = explode("\n", trim($csv));

    expect($lines)->toHaveLength(2)
        ->and($lines[1])->toContain($enrollment->student->user->name)
        ->and($lines[1])->toContain('50');

    $row = str_getcsv($lines[1], ',', '"', '\\');

    expect($row[2])->toBe('2')
        ->and($row[3])->toBe('1')
        ->and($row[4])->toBe('1')
        ->and($row[5])->toBe('0')
        ->and($row[6])->toBe('0')
        ->and($row[7])->toBe('50');
});

test('the grades report has one column per assessment plus a final letter', function () {
    [$term, $section, $enrollment] = reportTermSectionEnrollment();

    $quiz = Assessment::query()->create([
        'section_id' => $section->id,
        'title' => 'Quiz 1',
        'type' => 'quiz',
        'weight' => 20,
        'max_score' => 50,
    ]);

    app(SaveSectionScores::class)->handle($section, [
        $quiz->id => [$enrollment->id => 40],
    ]);

    $csv = app(ReportService::class)->gradesReport($section);

    $lines = explode("\n", trim($csv));

    expect($lines)->toHaveLength(2)
        ->and($lines[0])->toContain('Student', 'Quiz 1', 'Final %', 'Letter')
        ->and($lines[1])->toContain($enrollment->student->user->name, '40');
});
