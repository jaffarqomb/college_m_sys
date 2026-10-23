<?php

namespace App\Actions\Academic;

use App\Models\Assessment;
use App\Models\CourseSection;
use App\Models\Enrollment;
use App\Models\Grade;
use App\Models\User;
use App\Notifications\GradesUpdated;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Saves entered assessment scores for a section.
 *
 * Accepts `scoresByAssessment = [assessment_id => [enrollment_id => score|null, ...]]`
 * and upserts one grade row per enrollment. Sparse maps simply leave other
 * students untouched; a null score keeps the row score empty (unscored).
 * ValidationException messages are keyed under "scores".
 */
class SaveSectionScores
{
    /**
     * @param  array<int|string, array<int|string, float|string|null>>  $scoresByAssessment
     */
    public function handle(CourseSection $section, array $scoresByAssessment): void
    {
        $assessments = $section->assessments()->get()->keyBy('id');
        $enrollments = $section->enrollments()->where('status', 'active')->get()->keyBy('id');

        $messages = [];

        foreach ($scoresByAssessment as $assessmentId => $scores) {
            $assessment = $assessments[(int) $assessmentId] ?? null;

            if ($assessment === null) {
                $messages['scores'] = "Assessment #{$assessmentId} does not belong to this section.";

                continue;
            }

            foreach ($scores as $enrollmentId => $score) {
                $enrollment = $enrollments[(int) $enrollmentId] ?? null;

                if ($enrollment === null) {
                    $messages['scores'] = 'Scores include a student who is not enrolled in this section.';

                    continue;
                }

                if ($score === null || $score === '') {
                    continue;
                }

                if (! is_numeric($score)) {
                    $messages['scores'] = "Score for assessment \"{$assessment->title}\" must be a number.";

                    continue;
                }

                if ((float) $score < 0 || (float) $score > (float) $assessment->max_score) {
                    $maximum = (float) $assessment->max_score;

                    $messages['scores'] = "Score for \"{$assessment->title}\" must be between 0 and {$maximum}.";

                    continue;
                }
            }
        }

        if ($messages !== []) {
            throw ValidationException::withMessages($messages);
        }

        DB::transaction(function () use ($scoresByAssessment): void {
            foreach ($scoresByAssessment as $assessmentId => $scores) {
                foreach ($scores as $enrollmentId => $score) {
                    if ($score === null || $score === '') {
                        continue;
                    }

                    $this->upsertGrade((int) $assessmentId, (int) $enrollmentId, (float) $score);
                }
            }
        });

        $this->notifyEnrolledStudents($section);
    }

    private function upsertGrade(int $assessmentId, int $enrollmentId, float $score): void
    {
        Grade::query()->updateOrCreate(
            [
                'assessment_id' => $assessmentId,
                'enrollment_id' => $enrollmentId,
            ],
            ['score' => $score],
        );
    }

    /**
     * Notify each actively enrolled student of the section that scores
     * were entered (one queued notification per recipient).
     */
    private function notifyEnrolledStudents(CourseSection $section): void
    {
        $section->enrollments()
            ->where('status', 'active')
            ->with('student.user')
            ->get()
            ->pluck('student.user')
            ->each(fn (User $user) => $user->notify(new GradesUpdated($section)));
    }
}
