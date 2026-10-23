<?php

namespace App\Services\Academic;

use App\Enums\AttendanceStatus;
use App\Enums\LetterGrade;
use App\Models\AcademicTerm;
use App\Models\Enrollment;
use App\Models\Student;
use Illuminate\Support\Collection;

/**
 * Computes attendance percentages, weighted final grades, letters and GPA.
 *
 * Grading conventions:
 *  - Each assessment contributes (score / max_score) x weight to the final
 *    grade; only assessments with an entered score count, and the denominator
 *    is the summed weight of the graded assessments ("graded so far").
 *  - Attendance percentage counts only held sessions; excused absences do
 *    not count against the student.
 *  - GPA weights letter points by the course credit hours.
 */
class GradeService
{
    /**
     * Letter for a percentage on the 100-point scale.
     */
    public function letterFor(float $percentage): LetterGrade
    {
        $percentage = round($percentage, 2);

        foreach ([LetterGrade::A, LetterGrade::B, LetterGrade::C, LetterGrade::D] as $grade) {
            if ($percentage >= $grade->minPercentage()) {
                return $grade;
            }
        }

        return LetterGrade::F;
    }

    /**
     * Attendance percentage for an enrollment, or null without records.
     *
     * @return float|null percentage 0–100 rounded to 2 decimals
     */
    public function attendancePercent(Enrollment $enrollment): ?float
    {
        $total = $enrollment->attendanceRecords()
            ->whereHas('classSession', fn ($query) => $query->held())
            ->count();

        if ($total === 0) {
            return null;
        }

        $attended = $enrollment->attendanceRecords()
            ->whereHas('classSession', fn ($query) => $query->held())
            ->whereIn('status', [AttendanceStatus::Present->value, AttendanceStatus::Excused->value])
            ->count();

        return round(($attended / $total) * 100, 2);
    }

    /**
     * Weighted final grade for an enrollment, or null without scores.
     *
     * @return array{percentage: float, letter: LetterGrade, points: float}|null
     */
    public function weightedFinalGrade(Enrollment $enrollment): ?array
    {
        $graded = collect($enrollment->grades)
            ->filter(fn ($grade) => $grade->score !== null)
            ->values();

        if ($graded->isEmpty()) {
            return null;
        }

        $totalWeight = 0.0;
        $points = 0.0;

        foreach ($graded as $grade) {
            $assessment = $grade->assessment;

            $weight = max(0.0, (float) $assessment->weight);
            $totalWeight += $weight;
            $points += $grade->percentage() * $weight;
        }

        if ($totalWeight <= 0.0) {
            return [
                'percentage' => round($points, 2),
                'letter' => $this->letterFor($points),
                'points' => $this->letterFor($points)->points(),
            ];
        }

        $percentage = round($points / $totalWeight, 2);
        $letter = $this->letterFor($percentage);

        return [
            'percentage' => $percentage,
            'letter' => $letter,
            'points' => $letter->points(),
        ];
    }

    /**
     * GPA for a student within a term, or null without graded courses.
     */
    public function termGpa(Student $student, AcademicTerm $term): ?float
    {
        $weighted = $this->weightedPoints($this->gradedEnrollments(
            $student->enrollments()->active()->whereHas('section', fn ($query) => $query->where('term_id', $term->id))->get(),
        ));

        return $weighted === null ? null : (round($weighted, 2));
    }

    /**
     * GPA across every term for a student, or null without graded courses.
     */
    public function cumulativeGpa(Student $student): ?float
    {
        $weighted = $this->weightedPoints($this->gradedEnrollments(
            $student->enrollments()->active()->with('section.course')->get(),
        ));

        return $weighted === null ? null : round($weighted, 2);
    }

    /**
     * Transcript rows grouped by term for a student.
     *
     * Each row is an array of enrollment summaries plus the term GPA:
     * `["term" => AcademicTerm, "enrollments" => list<array>, "gpa" => ?float]`.
     * Dropped enrollments are omitted.
     *
     * @return list<array{term: AcademicTerm, enrollments: list<array{section_id: int, code: string, course: string, credits: int, percentage: float|null, letter: LetterGrade|null, points: float|null, status: string}>, gpa: float|null}>
     */
    public function transcript(Student $student): array
    {
        $enrollments = $student->enrollments()
            ->with('section.course', 'section.term', 'grades.assessment', 'attendanceRecords')
            ->orderBy('id')
            ->get()
            ->filter(fn (Enrollment $enrollment): bool => $enrollment->status !== 'dropped');

        $terms = $enrollments
            ->map(fn (Enrollment $enrollment): AcademicTerm => $enrollment->section->term)
            ->unique('id')
            ->sortByDesc('id');

        return array_values($terms
            ->map(function (AcademicTerm $term) use ($enrollments, $student): array {
                $rows = array_values($enrollments
                    ->filter(fn (Enrollment $enrollment): bool => $enrollment->section->term_id === $term->id)
                    ->map(fn (Enrollment $enrollment): array => $this->transcriptRow($enrollment))
                    ->all());

                $gpa = $this->termGpa($student, $term);

                return [
                    'term' => $term,
                    'enrollments' => $rows,
                    'gpa' => $gpa,
                ];
            })
            ->all());
    }

    /**
     * @param  Collection<int, Enrollment>  $enrollments
     * @return Collection<int, Enrollment>
     */
    private function gradedEnrollments(Collection $enrollments): Collection
    {
        return $enrollments->filter(fn (Enrollment $enrollment): bool => $this->weightedFinalGrade($enrollment) !== null);
    }

    /**
     * Credit-weighted average of final grade points, or null without grades.
     *
     * @param  Collection<int, Enrollment>  $enrollments
     */
    private function weightedPoints(Collection $enrollments): ?float
    {
        $creditHours = 0.0;
        $points = 0.0;

        foreach ($enrollments as $enrollment) {
            /** @var array{percentage: float, letter: LetterGrade, points: float} $final */
            $final = $this->weightedFinalGrade($enrollment);

            $credits = max(1, (int) ($enrollment->section->course->credits ?? 1));
            $creditHours += $credits;
            $points += $credits * $final['points'];
        }

        if ($creditHours <= 0.0) {
            return null;
        }

        return $points / $creditHours;
    }

    /**
     * @return array{section_id: int, code: string, course: string, credits: int, percentage: float|null, letter: LetterGrade|null, points: float|null, status: string}
     */
    private function transcriptRow(Enrollment $enrollment): array
    {
        $final = $this->weightedFinalGrade($enrollment);

        return [
            'section_id' => $enrollment->section_id,
            'code' => $enrollment->section->fullCode(),
            'course' => $enrollment->section->course->title,
            'credits' => (int) $enrollment->section->course->credits,
            'percentage' => $final['percentage'] ?? null,
            'letter' => $final['letter'] ?? null,
            'points' => $final['points'] ?? null,
            'status' => $enrollment->status,
        ];
    }
}
