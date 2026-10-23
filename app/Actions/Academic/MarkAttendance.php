<?php

namespace App\Actions\Academic;

use App\Enums\AttendanceStatus;
use App\Models\AttendanceRecord;
use App\Models\ClassSession;
use App\Models\CourseSection;
use App\Models\Enrollment;
use App\Notifications\AttendanceAlert;
use App\Services\Academic\GradeService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Marks attendance for every active enrollment of a section on a date.
 *
 * Creates (or reuses) the held class session for the date and upserts one
 * attendance record per student with the submitted status map:
 * `[enrollment_id => AttendanceStatus|value-of<AttendanceStatus>]`.
 * ValidationException messages are keyed under "attendance".
 */
class MarkAttendance
{
    /**
     * @param  array<int|string, AttendanceStatus|string>  $statuses  enrollment id => status
     */
    public function handle(CourseSection $section, Carbon|string $date, array $statuses): ClassSession
    {
        $sessionDate = $date instanceof Carbon ? $date->copy()->startOfDay() : Carbon::parse($date);

        $enrollments = $section->enrollments()->where('status', 'active')->get();

        if ($enrollments->isEmpty()) {
            throw ValidationException::withMessages([
                'attendance' => 'This section has no active enrollments to mark.',
            ]);
        }

        foreach ($statuses as $enrollmentId => $status) {
            if (! $enrollments->contains('id', (int) $enrollmentId)) {
                throw ValidationException::withMessages([
                    'attendance' => 'Attendance includes a student who is not enrolled in this section.',
                ]);
            }

            if (! $status instanceof AttendanceStatus && AttendanceStatus::tryFrom((string) $status) === null) {
                throw ValidationException::withMessages([
                    'attendance' => "Unknown attendance status \"{$status}\".",
                ]);
            }
        }

        $session = DB::transaction(function () use ($section, $sessionDate, $statuses, $enrollments): ClassSession {
            $session = ClassSession::query()
                ->where('section_id', $section->id)
                ->whereDate('session_date', $sessionDate->toDateString())
                ->first();

            if ($session === null) {
                $session = ClassSession::query()->create([
                    'section_id' => $section->id,
                    'session_date' => $sessionDate->toDateString(),
                    'status' => 'held',
                ]);
            }

            $session->forceFill(['status' => 'held'])->save();

            foreach ($enrollments as $enrollment) {
                $raw = $statuses[$enrollment->id] ?? AttendanceStatus::Present->value;
                $status = match (true) {
                    $raw instanceof AttendanceStatus => $raw,
                    default => AttendanceStatus::from((string) $raw),
                };

                AttendanceRecord::query()->updateOrCreate(
                    [
                        'class_session_id' => $session->id,
                        'enrollment_id' => $enrollment->id,
                    ],
                    ['status' => $status],
                );
            }

            return $session;
        });

        $this->sendAttendanceAlerts($section, $statuses);

        return $session->refresh();
    }

    /**
     * Alert an enrolled student (queued, at most once every 14 days per
     * enrollment) when their attendance percentage is below the configured
     * threshold after this session's records.
     *
     * @param  array<int|string, AttendanceStatus|string>  $statuses
     */
    private function sendAttendanceAlerts(CourseSection $section, array $statuses): void
    {
        $threshold = (float) config('college.attendance.alert_threshold');

        if ($threshold <= 0.0) {
            return;
        }

        foreach (array_keys($statuses) as $enrollmentId) {
            /** @var Enrollment|null $enrollment */
            $enrollment = Enrollment::query()->find((int) $enrollmentId);

            if ($enrollment === null) {
                continue;
            }

            $percentage = app(GradeService::class)->attendancePercent($enrollment->refresh());

            if ($percentage === null || $percentage >= $threshold) {
                continue;
            }

            $alerted = Cache::add(
                "attendance-alert:{$enrollment->id}",
                now()->toDateTimeString(),
                now()->addDays(14),
            );

            if (! $alerted) {
                continue;
            }

            $enrollment->student->user->notify(new AttendanceAlert($section->fullCode(), $percentage, $threshold));
        }
    }
}
