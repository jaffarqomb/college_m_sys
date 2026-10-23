<?php

namespace App\Actions\Academic;

use App\Models\CourseSection;
use App\Models\Enrollment;
use App\Models\Student;
use App\Notifications\EnrollmentConfirmed;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Enrolls an active student into an open section of the current term,
 * enforcing capacity, deadlines, duplicates, prerequisites and schedule
 * conflicts. ValidationException messages are keyed under "enroll".
 */
class EnrollInSection
{
    public function handle(Student $student, CourseSection $section): Enrollment
    {
        $this->assertSectionIsOpen($section);
        $this->assertStudentIsActive($student);
        $this->assertTermAcceptsRegistrations($section);
        $this->assertSeatsRemain($section);
        $this->assertNotAlreadyEnrolled($student, $section);
        $this->assertPrerequisitesSatisfied($student, $section);
        $this->assertNoScheduleConflict($student, $section);

        $enrollment = DB::transaction(function () use ($student, $section): Enrollment {
            return Enrollment::query()->create([
                'student_id' => $student->id,
                'section_id' => $section->id,
                'status' => 'active',
                'enrolled_at' => now(),
            ]);
        });

        $this->notifyStudent($enrollment->loadMissing('student'));

        return $enrollment;
    }

    /**
     * Notify the student (queued) that their enrollment is confirmed.
     */
    private function notifyStudent(Enrollment $enrollment): void
    {
        $enrollment->student->user->notify(new EnrollmentConfirmed($enrollment->load('section.course', 'section.term')));
    }

    private function assertSectionIsOpen(CourseSection $section): void
    {
        if ($section->status !== 'open') {
            throw ValidationException::withMessages([
                'enroll' => 'This section is not open for enrollment.',
            ]);
        }
    }

    private function assertStudentIsActive(Student $student): void
    {
        if ($student->status !== 'active') {
            throw ValidationException::withMessages([
                'enroll' => 'Only active students can enroll in sections.',
            ]);
        }
    }

    private function assertTermAcceptsRegistrations(CourseSection $section): void
    {
        $term = $section->term;

        if (! $term->is_current) {
            throw ValidationException::withMessages([
                'enroll' => 'Enrollment is only available for the current term.',
            ]);
        }

        if (! $term->registrationIsOpen()) {
            throw ValidationException::withMessages([
                'enroll' => 'The registration deadline for this term has passed.',
            ]);
        }
    }

    private function assertSeatsRemain(CourseSection $section): void
    {
        if ($section->remainingSeats() < 1) {
            throw ValidationException::withMessages([
                'enroll' => 'This section is full.',
            ]);
        }
    }

    private function assertNotAlreadyEnrolled(Student $student, CourseSection $section): void
    {
        // Aligns with the (student_id, section_id) unique index, which covers
        // dropped enrollments too — re-enrollment after a drop is not allowed.
        $isEnrolled = $student->enrollments()
            ->where('section_id', $section->id)
            ->exists();

        if ($isEnrolled) {
            throw ValidationException::withMessages([
                'enroll' => 'You are already enrolled in this section.',
            ]);
        }
    }

    private function assertPrerequisitesSatisfied(Student $student, CourseSection $section): void
    {
        $prerequisites = $section->course->prerequisites;

        if ($prerequisites->isEmpty()) {
            return;
        }

        $completedCourseIds = $student->enrollments()
            ->where('status', 'completed')
            ->whereHas('section', fn ($query) => $query->whereIn('course_id', $prerequisites->pluck('id')))
            ->with('section')
            ->get()
            ->pluck('section.course_id')
            ->unique();

        $missing = $prerequisites
            ->reject(fn ($prerequisite) => $completedCourseIds->contains($prerequisite->id))
            ->pluck('code')
            ->implode(', ');

        if ($missing !== '') {
            throw ValidationException::withMessages([
                'enroll' => sprintf('You must first complete: %s.', $missing),
            ]);
        }
    }

    private function assertNoScheduleConflict(Student $student, CourseSection $section): void
    {
        $requestedSlots = $section->sectionSchedules()->get();

        if ($requestedSlots->isEmpty()) {
            return;
        }

        $conflict = $student->enrollments()
            ->active()
            ->whereHas('section', fn ($query) => $query->where('term_id', $section->term_id))
            ->whereHas('section.sectionSchedules', fn ($query) => $query->whereIn('day_of_week', $requestedSlots->pluck('day_of_week')->unique()))
            ->with('section.sectionSchedules')
            ->get()
            ->flatMap(fn ($enrollment) => $enrollment->section->sectionSchedules)
            ->first(function ($existingSlot) use ($requestedSlots) {
                return $requestedSlots->contains(fn ($requestedSlot) => $requestedSlot->day_of_week === $existingSlot->day_of_week
                    && $requestedSlot->overlaps($existingSlot)
                );
            });

        if ($conflict !== null) {
            throw ValidationException::withMessages([
                'enroll' => 'This section conflicts with your existing schedule.',
            ]);
        }
    }
}
