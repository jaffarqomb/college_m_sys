<?php

namespace App\Actions\Timetable;

use App\Enums\TimetableGenerationStatus;
use App\Models\CourseSection;
use App\Models\SectionSchedule;
use App\Models\TimetableGeneration;
use App\Models\User;
use App\Notifications\TimetablePublished;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Materialize a draft generation into SectionSchedule rows.
 *
 * Sections that appear in the draft have their schedules replaced inside a
 * single transaction; sections left out of the draft (e.g. conflicts) keep
 * whatever schedules they already own. The generation is marked published.
 */
class PublishTimetableGeneration
{
    /**
     * @return array<int, int> the section ids that were replaced
     *
     * @throws ValidationException when the generation is not a draft
     */
    public function handle(TimetableGeneration $generation): array
    {
        if ($generation->status !== TimetableGenerationStatus::Draft) {
            throw ValidationException::withMessages([
                'timetable' => 'Only draft generations can be published.',
            ]);
        }

        $slotsBySection = collect($generation->assignments)
            ->mapWithKeys(fn (array $assignment): array => [
                (int) $assignment['section_id'] => $assignment['slots'],
            ]);

        $replaced = [];

        DB::transaction(function () use ($generation, $slotsBySection, &$replaced): void {
            foreach ($slotsBySection as $sectionId => $slots) {
                $section = CourseSection::query()->where('term_id', $generation->term_id)->find($sectionId);

                if ($section === null || $slots === []) {
                    continue;
                }

                $section->sectionSchedules()->delete();

                foreach ($slots as $slot) {
                    SectionSchedule::query()->create([
                        'section_id' => $section->id,
                        'room_id' => $slot['room_id'],
                        'day_of_week' => (int) $slot['day'],
                        'start_time' => $slot['start'],
                        'end_time' => $slot['end'],
                    ]);
                }

                $replaced[] = $sectionId;
            }

            $generation->update([
                'status' => TimetableGenerationStatus::Published,
                'published_at' => now(),
            ]);
        });

        $this->notifyAffectedPeople($generation, $replaced);

        return $replaced;
    }

    /**
     * Notify the students and instructors of the replaced sections (one
     * queued notification per recipient, de-duplicated by user id).
     *
     * @param  array<int, int>  $replaced
     */
    private function notifyAffectedPeople(TimetableGeneration $generation, array $replaced): void
    {
        if ($replaced === []) {
            return;
        }

        $sectionIds = array_map(intval(...), $replaced);

        $students = User::query()
            ->whereHas('studentProfile.enrollments', fn ($query) => $query
                ->where('status', 'active')
                ->whereHas('section', fn ($sectionQuery) => $sectionQuery->whereIn('course_sections.id', $sectionIds)))
            ->get();

        $instructors = User::query()
            ->whereHas('instructorProfile.courseSections', fn ($sectionQuery) => $sectionQuery->whereIn('course_sections.id', $sectionIds))
            ->get();

        $recipients = $students
            ->merge($instructors)
            ->unique('id');

        $notification = new TimetablePublished($generation->term);

        $recipients->each(fn (User $user) => $user->notify($notification));
    }
}
