<?php

namespace App\Services\Reports;

use App\Enums\AttendanceStatus;
use App\Models\AcademicTerm;
use App\Models\AttendanceRecord;
use App\Models\CourseSection;
use App\Models\Enrollment;
use App\Services\Academic\GradeService;
use fputcsv;

use function rewind;
use function stream_get_contents;

/**
 * Builds CSV exports for the registrar's recurring data returns. Every
 * method returns the CSV as a string: the first row is the header row and
 * values are RFC 4180-style comma-separated fields (via fputcsv).
 */
class ReportService
{
    /**
     * Enrollments of a term, one row per enrollment.
     */
    public function enrollmentsReport(AcademicTerm $term): string
    {
        $rows = collect();

        CourseSection::query()
            ->where('term_id', $term->id)
            ->with(['course', 'enrollments.student.user'])
            ->orderBy('course_id')
            ->orderBy('section_number')
            ->get()
            ->each(function (CourseSection $section) use ($rows): void {
                $section->enrollments->each(function (Enrollment $enrollment) use ($rows, $section): void {
                    $rows->push([
                        $section->fullCode(),
                        $section->course->title,
                        $enrollment->student->student_number,
                        $enrollment->student->user->name,
                        $enrollment->status,
                        $enrollment->enrolled_at->toDateString(),
                    ]);
                });
            });

        return $this->toCsv(
            ['Section', 'Course', 'Student number', 'Student name', 'Status', 'Enrolled at'],
            $rows,
        );
    }

    /**
     * Invoices of a term with the paid-so-far sums per invoice.
     */
    public function invoicesReport(AcademicTerm $term): string
    {
        $rows = $term->invoices()
            ->with(['student.user'])
            ->withSum(['payments as paid_sum' => fn ($query) => $query->where('status', 'succeeded')], 'amount')
            ->orderBy('number')
            ->get()
            ->map(fn ($invoice): array => [
                $invoice->number,
                $invoice->student->user->name,
                $invoice->student->student_number,
                $invoice->status,
                (float) $invoice->total,
                (float) ($invoice->paid_sum ?? 0),
                round((float) $invoice->total - (float) ($invoice->paid_sum ?? 0), 3),
                $invoice->due_date->toDateString(),
            ]);

        return $this->toCsv(
            ['Invoice', 'Student', 'Student number', 'Status', 'Total', 'Paid', 'Balance', 'Due date'],
            $rows,
        );
    }

    /**
     * Succeeded payments received against term invoices.
     */
    public function paymentsReport(AcademicTerm $term): string
    {
        $rows = collect();

        $term->invoices()->with(['payments', 'student.user'])->get()
            ->each(function ($invoice) use ($rows): void {
                $invoice->payments
                    ->where('status', 'succeeded')
                    ->each(fn ($payment) => $rows->push([
                        $invoice->number,
                        $invoice->student->user->name,
                        $payment->method,
                        (float) $payment->amount,
                        $payment->reference ?? '',
                        $payment->paid_at?->toDateString() ?? '',
                    ]));
            });

        return $this->toCsv(
            ['Invoice', 'Student', 'Method', 'Amount', 'Reference', 'Paid at'],
            $rows,
        );
    }

    /**
     * Per-student attendance breakdown for a section.
     */
    public function attendanceReport(CourseSection $section): string
    {
        $service = app(GradeService::class);

        $rows = $section->enrollments()
            ->where('status', 'active')
            ->with(['attendanceRecords.classSession', 'student.user'])
            ->get()
            ->map(fn (Enrollment $enrollment): array => [
                $enrollment->student->user->name,
                $enrollment->student->student_number,
                $this->countHeld($enrollment->attendanceRecords),
                $this->countStatus($enrollment->attendanceRecords, AttendanceStatus::Present),
                $this->countStatus($enrollment->attendanceRecords, AttendanceStatus::Absent),
                $this->countStatus($enrollment->attendanceRecords, AttendanceStatus::Excused),
                $this->countStatus($enrollment->attendanceRecords, AttendanceStatus::Late),
                $service->attendancePercent($enrollment) ?? '',
            ]);

        return $this->toCsv(
            ['Student', 'Student number', 'Held sessions', 'Present', 'Absent', 'Excused', 'Late', 'Attendance %'],
            $rows,
        );
    }

    /**
     * Section gradebook: one row per enrollment with per-assessment scores.
     */
    public function gradesReport(CourseSection $section): string
    {
        $service = app(GradeService::class);
        $assessments = $section->assessments()->orderBy('id')->get();

        $rows = $section->enrollments()
            ->where('status', 'active')
            ->with(['grades.assessment', 'student.user'])
            ->get()
            ->map(function (Enrollment $enrollment) use ($assessments, $service): array {
                $gradesByAssessment = $enrollment->grades
                    ->filter(fn ($grade) => $grade->score !== null)
                    ->keyBy('assessment_id');

                $row = [
                    $enrollment->student->user->name,
                    $enrollment->student->student_number,
                ];

                foreach ($assessments as $assessment) {
                    $row[] = isset($gradesByAssessment[$assessment->id])
                        ? (float) $gradesByAssessment[$assessment->id]->score
                        : '';
                }

                $final = $service->weightedFinalGrade($enrollment);

                if ($final !== null) {
                    $row[] = $final['percentage'];
                    $row[] = $final['letter']->label();
                } else {
                    $row[] = '';
                    $row[] = '';
                }

                return $row;
            });

        $headers = ['Student', 'Student number', ...$assessments->pluck('title')->values()->all(), 'Final %', 'Letter'];

        return $this->toCsv($headers, $rows);
    }

    /**
     * @param  iterable<AttendanceRecord>  $records
     */
    private function countHeld(iterable $records): int
    {
        return $this->countRecords($records, fn (AttendanceRecord $record): bool => true);
    }

    /**
     * @param  iterable<AttendanceRecord>  $records
     */
    private function countStatus(iterable $records, AttendanceStatus $status): int
    {
        return $this->countRecords($records, fn (AttendanceRecord $record): bool => $record->status === $status);
    }

    /**
     * @param  iterable<AttendanceRecord>  $records
     */
    private function countRecords(iterable $records, callable $of): int
    {
        $count = 0;

        foreach ($records as $record) {
            if ($record->classSession->status === 'held' && $of($record)) {
                $count++;
            }
        }

        return $count;
    }

    /**
     * Render rows as CSV (RFC 4180 via fputcsv, comma separator).
     *
     * @param  list<string>  $headers
     * @param  iterable<int, array<int, string|int|float|null>>  $rows
     */
    private function toCsv(array $headers, iterable $rows): string
    {
        $stream = fopen('php://temp', 'r+');

        if ($stream === false) {
            return '';
        }

        fputcsv($stream, $headers, ',', '"', '\\');

        foreach ($rows as $row) {
            fputcsv($stream, $row, ',', '"', '\\');
        }

        rewind($stream);
        $csv = (string) stream_get_contents($stream);
        fclose($stream);

        return $csv;
    }
}
