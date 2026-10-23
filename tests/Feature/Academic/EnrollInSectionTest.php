<?php

use App\Actions\Academic\EnrollInSection;
use App\Models\AcademicTerm;
use App\Models\Course;
use App\Models\CourseSection;
use App\Models\Enrollment;
use App\Models\Instructor;
use App\Models\Room;
use App\Models\SectionSchedule;
use App\Models\Student;
use Carbon\CarbonInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;

use function Pest\Laravel\assertDatabaseHas;

uses(RefreshDatabase::class);

test('a student can enroll in an open section of the current term', function () {
    $section = CourseSection::factory()->inCurrentTerm()->create(['capacity' => 5]);

    $enrollment = app(EnrollInSection::class)->handle(
        Student::factory()->create(),
        $section,
    );

    expect($enrollment->status)->toBe('active');
    expect($enrollment->enrolled_at)->toBeInstanceOf(CarbonInterface::class);

    assertDatabaseHas('enrollments', [
        'student_id' => $enrollment->student_id,
        'section_id' => $section->id,
        'status' => 'active',
    ]);
});

test('running the same enrollment twice is rejected', function () {
    $section = CourseSection::factory()->inCurrentTerm()->create();
    $student = Student::factory()->create();

    app(EnrollInSection::class)->handle($student, $section);

    expect(fn () => app(EnrollInSection::class)->handle($student, $section))
        ->toThrow(ValidationException::class, 'You are already enrolled in this section.');
});

test('re-enrolling after dropping a section is rejected', function () {
    $section = CourseSection::factory()->inCurrentTerm()->create();
    $student = Student::factory()->create();

    Enrollment::factory()->create([
        'student_id' => $student->id,
        'section_id' => $section->id,
        'status' => 'dropped',
    ]);

    expect(fn () => app(EnrollInSection::class)->handle($student, $section))
        ->toThrow(ValidationException::class, 'You are already enrolled in this section.');
});

test('a full section cannot accept enrollment', function () {
    $section = CourseSection::factory()->inCurrentTerm()->create(['capacity' => 1]);
    $student = Student::factory()->create();

    Enrollment::query()->create([
        'student_id' => Student::factory()->create()->id,
        'section_id' => $section->id,
        'status' => 'active',
        'enrolled_at' => now(),
    ]);

    expect(fn () => app(EnrollInSection::class)->handle($student, $section))
        ->toThrow(ValidationException::class, 'This section is full.');
});

test('enrollment in a closed section is rejected', function () {
    $section = CourseSection::factory()->inCurrentTerm()->closed()->create();
    $student = Student::factory()->create();

    expect(fn () => app(EnrollInSection::class)->handle($student, $section))
        ->toThrow(ValidationException::class, 'This section is not open for enrollment.');
});

test('sections outside the current term cannot accept enrollment', function () {
    $pastTerm = AcademicTerm::factory()->create([
        'is_current' => false,
        'start_date' => now()->subMonths(8),
        'end_date' => now()->subMonths(4),
    ]);

    $section = CourseSection::factory()->create(['term_id' => $pastTerm->id]);
    $student = Student::factory()->create();

    expect(fn () => app(EnrollInSection::class)->handle($student, $section))
        ->toThrow(ValidationException::class, 'Enrollment is only available for the current term.');
});

test('enrollment is rejected after the registration deadline', function () {
    $term = AcademicTerm::factory()->current()->create([
        'registration_deadline' => now()->subDay(),
    ]);

    $section = CourseSection::factory()->create(['term_id' => $term->id]);
    $student = Student::factory()->create();

    expect(fn () => app(EnrollInSection::class)->handle($student, $section))
        ->toThrow(ValidationException::class, 'The registration deadline for this term has passed.');
});

test('an inactive student cannot enroll', function () {
    $section = CourseSection::factory()->inCurrentTerm()->create();
    $student = Student::factory()->graduated()->create();

    expect(fn () => app(EnrollInSection::class)->handle($student, $section))
        ->toThrow(ValidationException::class, 'Only active students can enroll in sections.');
});

test('unmet prerequisites are named in the rejection', function () {
    $prerequisite = Course::factory()->create(['code' => 'CS101']);
    $course = Course::factory()->create(['code' => 'CS201']);
    $course->prerequisites()->sync([$prerequisite->id]);

    $section = CourseSection::factory()->inCurrentTerm()->create(['course_id' => $course->id]);
    $student = Student::factory()->create();

    try {
        app(EnrollInSection::class)->handle($student, $section);
        $this->fail('Expected ValidationException was not thrown.');
    } catch (ValidationException $exception) {
        expect($exception->errors()['enroll'][0])->toContain('CS101');
    }
});

test('met prerequisites allow enrollment', function () {
    $prerequisite = Course::factory()->create(['code' => 'CS101']);
    $course = Course::factory()->create(['code' => 'CS201']);
    $course->prerequisites()->sync([$prerequisite->id]);

    $prerequisiteSection = CourseSection::factory()->inCurrentTerm()->create(['course_id' => $prerequisite->id]);
    $student = Student::factory()->create();

    Enrollment::query()->create([
        'student_id' => $student->id,
        'section_id' => $prerequisiteSection->id,
        'status' => 'completed',
        'enrolled_at' => now(),
    ]);

    $section = CourseSection::factory()->inCurrentTerm()->create(['course_id' => $course->id]);

    $enrollment = app(EnrollInSection::class)->handle($student, $section);

    expect($enrollment->status)->toBe('active');
});

test('overlapping schedules block enrollment', function () {
    $term = AcademicTerm::factory()->current()->create();
    $room = Room::factory()->create();

    $busySection = CourseSection::factory()->create(['term_id' => $term->id]);
    SectionSchedule::factory()->at(1, '09:00:00', '10:30:00', $room)->create(['section_id' => $busySection->id]);

    $student = Student::factory()->create();
    Enrollment::query()->create([
        'student_id' => $student->id,
        'section_id' => $busySection->id,
        'status' => 'active',
        'enrolled_at' => now(),
    ]);

    $newSection = CourseSection::factory()->create([
        'term_id' => $term->id,
        'instructor_id' => Instructor::factory(),
    ]);
    SectionSchedule::factory()->at(1, '10:00:00', '11:30:00', $room)->create(['section_id' => $newSection->id]);

    expect(fn () => app(EnrollInSection::class)->handle($student, $newSection))
        ->toThrow(ValidationException::class, 'This section conflicts with your existing schedule.');
});

test('non-overlapping schedules allow enrollment', function () {
    $term = AcademicTerm::factory()->current()->create();
    $room = Room::factory()->create();

    $busySection = CourseSection::factory()->create(['term_id' => $term->id]);
    SectionSchedule::factory()->at(1, '09:00:00', '10:30:00', $room)->create(['section_id' => $busySection->id]);

    $student = Student::factory()->create();
    Enrollment::query()->create([
        'student_id' => $student->id,
        'section_id' => $busySection->id,
        'status' => 'active',
        'enrolled_at' => now(),
    ]);

    $newSection = CourseSection::factory()->create(['term_id' => $term->id]);
    SectionSchedule::factory()->at(2, '09:00:00', '10:30:00', $room)->create(['section_id' => $newSection->id]);

    $enrollment = app(EnrollInSection::class)->handle($student, $newSection);

    expect($enrollment->status)->toBe('active');
});

test('only one term is current at a time', function () {
    // Guards the "current term" invariant: only one is_current term exists after factory usage.
    $termA = AcademicTerm::factory()->current()->create();
    $termB = AcademicTerm::factory()->create(['is_current' => false]);

    expect(AcademicTerm::query()->current()->count())->toBe(1);
    expect($termB->refresh()->is_current)->toBeFalse();
});
