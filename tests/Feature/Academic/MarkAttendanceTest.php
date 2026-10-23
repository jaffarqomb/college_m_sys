<?php

use App\Actions\Academic\MarkAttendance;
use App\Enums\AttendanceStatus;
use App\Models\AttendanceRecord;
use App\Models\ClassSession;
use App\Models\CourseSection;
use App\Models\Enrollment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;

use function Pest\Laravel\assertDatabaseHas;

uses(RefreshDatabase::class);

test('marking attendance upserts one held session and one record per active enrollment', function () {
    $section = CourseSection::factory()->inCurrentTerm()->create();

    $first = Enrollment::factory()->create(['section_id' => $section->id]);
    $second = Enrollment::factory()->create(['section_id' => $section->id]);
    // Dropped enrollments must not receive records.
    Enrollment::factory()->dropped()->create(['section_id' => $section->id]);

    $date = now()->subWeek();

    $session = (new MarkAttendance)->handle($section, $date, [
        $first->id => 'present',
        $second->id => 'absent',
    ]);

    expect($session->status)->toBe('held')
        ->and(ClassSession::query()->count())->toBe(1);

    assertDatabaseHas(AttendanceRecord::class, [
        'class_session_id' => $session->id,
        'enrollment_id' => $first->id,
        'status' => 'present',
    ]);
    assertDatabaseHas(AttendanceRecord::class, [
        'class_session_id' => $session->id,
        'enrollment_id' => $second->id,
        'status' => 'absent',
    ]);
    expect(AttendanceRecord::query()->count())->toBe(2);
});

test('re-marking the same section and date reuses the session and overwrites statuses', function () {
    $section = CourseSection::factory()->inCurrentTerm()->create();

    $enrollment = Enrollment::factory()->create(['section_id' => $section->id]);
    $date = now()->subDays(3);

    (new MarkAttendance)->handle($section, $date, [$enrollment->id => 'absent']);
    $session = (new MarkAttendance)->handle($section, $date, [$enrollment->id => 'late']);

    expect(ClassSession::query()->count())->toBe(1)
        ->and($session->attendanceRecords()->count())->toBe(1)
        ->and($session->attendanceRecords()->first()->status->value)->toBe('late');
});

test('marking attendance for a student of another section is rejected', function () {
    $section = CourseSection::factory()->inCurrentTerm()->create();

    Enrollment::factory()->create(['section_id' => $section->id]);

    $foreign = Enrollment::factory()->create([
        'section_id' => CourseSection::factory()->create()->id,
    ]);

    new MarkAttendance()->handle($section, now(), [$foreign->id => 'present']);
})->throws(ValidationException::class, 'Attendance includes a student who is not enrolled in this section.');

test('unknown status values are rejected', function () {
    $section = CourseSection::factory()->inCurrentTerm()->create();

    $enrollment = Enrollment::factory()->create(['section_id' => $section->id]);

    new MarkAttendance()->handle($section, now(), [$enrollment->id => 'sleeping']);
})->throws(ValidationException::class);

test('marking attendance for a section without active enrollments is rejected', function () {
    $section = CourseSection::factory()->inCurrentTerm()->create();

    new MarkAttendance()->handle($section, now(), []);
})->throws(ValidationException::class, 'This section has no active enrollments to mark.');

test('accepts an enum instance exactly like its string back value', function () {
    $section = CourseSection::factory()->inCurrentTerm()->create();

    $enrollment = Enrollment::factory()->create(['section_id' => $section->id]);

    $session = new MarkAttendance()->handle(
        $section,
        now(),
        [$enrollment->id => AttendanceStatus::Excused],
    );

    assertDatabaseHas(AttendanceRecord::class, [
        'class_session_id' => $session->id,
        'enrollment_id' => $enrollment->id,
        'status' => 'excused',
    ]);
});
