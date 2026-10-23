<?php

use App\Actions\Academic\MarkAttendance;
use App\Enums\LetterGrade;
use App\Models\AcademicTerm;
use App\Models\Assessment;
use App\Models\AttendanceRecord;
use App\Models\ClassSession;
use App\Models\Course;
use App\Models\CourseSection;
use App\Models\Enrollment;
use App\Models\Grade;
use App\Models\Student;
use App\Services\Academic\GradeService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * @return array{0: AcademicTerm, 1: Course, 2: CourseSection, 3: Student, 4: Enrollment}
 */
function grdSetup(int $credits = 3): array
{
    $term = AcademicTerm::factory()->current()->create();

    $course = Course::factory()->create(['credits' => $credits]);

    $section = CourseSection::factory()->create([
        'course_id' => $course->id,
        'term_id' => $term->id,
    ]);

    $student = Student::factory()->create();

    $enrollment = Enrollment::factory()->create([
        'student_id' => $student->id,
        'section_id' => $section->id,
    ]);

    return [$term, $course, $section, $student, $enrollment];
}

test('letter boundaries map exactly to thresholds', function (float $percentage, LetterGrade $expected) {
    $service = new GradeService;

    expect($service->letterFor($percentage))->toBe($expected)
        ->and($service->letterFor($percentage)->points())->toBe($expected->points());
})->with([
    '90 is an A' => [90.0, LetterGrade::A],
    '89.99 is a B' => [89.99, LetterGrade::B],
    '80 is a B' => [80.0, LetterGrade::B],
    '79.99 is a C' => [79.99, LetterGrade::C],
    '70 is a C' => [70.0, LetterGrade::C],
    '69.99 is a D' => [69.99, LetterGrade::D],
    '60 is a D' => [60.0, LetterGrade::D],
    '59.99 is an F' => [59.99, LetterGrade::F],
    '0 is an F' => [0.0, LetterGrade::F],
    '100 is an A' => [100.0, LetterGrade::A],
]);

test('weighted final grade uses the graded-so-far denominator', function () {
    [, , $section, , $enrollment] = grdSetup();

    $quiz = Assessment::query()->create([
        'section_id' => $section->id, 'title' => 'Quiz 1', 'type' => 'quiz', 'weight' => 10, 'max_score' => 25,
    ]);
    $assignment = Assessment::query()->create([
        'section_id' => $section->id, 'title' => 'Assignment 1', 'type' => 'assignment', 'weight' => 20, 'max_score' => 50,
    ]);
    // Not graded yet: excluded from both numerator and denominator.
    Assessment::query()->create([
        'section_id' => $section->id, 'title' => 'Final Exam', 'type' => 'final', 'weight' => 40, 'max_score' => 100,
    ]);

    Grade::query()->create(['assessment_id' => $quiz->id, 'enrollment_id' => $enrollment->id, 'score' => 20]);
    Grade::query()->create(['assessment_id' => $assignment->id, 'enrollment_id' => $enrollment->id, 'score' => 30]);

    $final = (new GradeService)->weightedFinalGrade($enrollment);

    // (0.8x10 + 0.6x20) / 30 = 66.67%.
    expect($final)->not->toBeNull()
        ->and($final['percentage'])->toBe(66.67)
        ->and($final['letter'])->toBe(LetterGrade::D)
        ->and($final['points'])->toBe(1.0);
});

test('weighted final grade is null without any scores', function () {
    [, , $section, , $enrollment] = grdSetup();

    Assessment::query()->create([
        'section_id' => $section->id, 'title' => 'Quiz 1', 'type' => 'quiz', 'weight' => 10, 'max_score' => 25,
    ]);

    expect((new GradeService)->weightedFinalGrade($enrollment))->toBeNull();
});

test('all-zero graded weights fall back to an F percentage', function () {
    [, , $section, , $enrollment] = grdSetup();

    $quiz = Assessment::query()->create([
        'section_id' => $section->id, 'title' => 'Quiz 1', 'type' => 'quiz', 'weight' => 0, 'max_score' => 25,
    ]);

    Grade::query()->create(['assessment_id' => $quiz->id, 'enrollment_id' => $enrollment->id, 'score' => 20]);

    $final = (new GradeService)->weightedFinalGrade($enrollment);

    expect($final)->not->toBeNull()
        ->and($final['letter'])->toBe(LetterGrade::F);
});

test('attendance percentage counts held sessions only and excused as attended', function () {
    [, , $section, , $enrollment] = grdSetup();

    $present = ClassSession::query()->create([
        'section_id' => $section->id, 'session_date' => now()->subWeeks(2), 'status' => 'held',
    ]);
    $excused = ClassSession::query()->create([
        'section_id' => $section->id, 'session_date' => now()->subWeeks(1), 'status' => 'held',
    ]);
    // A planned session must not count toward the denominator.
    $planned = ClassSession::query()->create([
        'section_id' => $section->id, 'session_date' => now()->addWeeks(1), 'status' => 'planned',
    ]);

    AttendanceRecord::query()->create(['class_session_id' => $present->id, 'enrollment_id' => $enrollment->id, 'status' => 'present']);
    AttendanceRecord::query()->create(['class_session_id' => $excused->id, 'enrollment_id' => $enrollment->id, 'status' => 'excused']);
    AttendanceRecord::query()->create(['class_session_id' => $planned->id, 'enrollment_id' => $enrollment->id, 'status' => 'present']);

    expect((new GradeService)->attendancePercent($enrollment))->toBe(100.0);
});

test('late attendance rates below full attendance', function () {
    [, , $section, , $enrollment] = grdSetup();

    $first = ClassSession::query()->create([
        'section_id' => $section->id, 'session_date' => now()->subWeeks(2), 'status' => 'held',
    ]);
    $second = ClassSession::query()->create([
        'section_id' => $section->id, 'session_date' => now()->subWeeks(1), 'status' => 'held',
    ]);

    AttendanceRecord::query()->create(['class_session_id' => $first->id, 'enrollment_id' => $enrollment->id, 'status' => 'present']);
    AttendanceRecord::query()->create(['class_session_id' => $second->id, 'enrollment_id' => $enrollment->id, 'status' => 'late']);

    expect((new GradeService)->attendancePercent($enrollment))->toBe(50.0);
});

test('attendance percentage is null without records', function () {
    [, , , , $enrollment] = grdSetup();

    expect((new GradeService)->attendancePercent($enrollment))->toBeNull();
});

test('mark attendance produces the partial percentage the service reports', function () {
    [, , $section, , $enrollment] = grdSetup();

    new MarkAttendance()->handle($section, now()->subWeeks(2), [$enrollment->id => 'present']);
    new MarkAttendance()->handle($section, now()->subWeeks(1), [$enrollment->id => 'late']);

    // Late does not count as attended under the current policy: 1 of 2.
    expect((new GradeService)->attendancePercent($enrollment))->toBe(50.0);
});

test('term GPA weights letter points by course credits', function () {
    $term = AcademicTerm::factory()->current()->create();

    $threeCredits = Course::factory()->create(['credits' => 3]);
    $twoCredits = Course::factory()->create(['credits' => 2]);

    $sectionA = CourseSection::factory()->create(['course_id' => $threeCredits->id, 'term_id' => $term->id]);
    $sectionB = CourseSection::factory()->create(['course_id' => $twoCredits->id, 'term_id' => $term->id]);

    $student = Student::factory()->create();

    $enrollmentA = Enrollment::factory()->create(['student_id' => $student->id, 'section_id' => $sectionA->id]);
    $enrollmentB = Enrollment::factory()->create(['student_id' => $student->id, 'section_id' => $sectionB->id]);

    $assessmentA = Assessment::query()->create([
        'section_id' => $sectionA->id, 'title' => 'Final', 'type' => 'final', 'weight' => 100, 'max_score' => 100,
    ]);
    $assessmentB = Assessment::query()->create([
        'section_id' => $sectionB->id, 'title' => 'Final', 'type' => 'final', 'weight' => 100, 'max_score' => 100,
    ]);

    // A: 95% -> A (4.0); B: 85% -> B (3.0). GPA = (3x4 + 2x3) / 5 = 3.6.
    Grade::query()->create(['assessment_id' => $assessmentA->id, 'enrollment_id' => $enrollmentA->id, 'score' => 95]);
    Grade::query()->create(['assessment_id' => $assessmentB->id, 'enrollment_id' => $enrollmentB->id, 'score' => 85]);

    $service = new GradeService;

    expect($service->termGpa($student, $term))->toBe(3.6)
        ->and($service->cumulativeGpa($student))->toBe(3.6);
});

test('term GPA is null without graded enrollments', function () {
    $term = AcademicTerm::factory()->current()->create();

    $section = CourseSection::factory()->create(['term_id' => $term->id]);
    $student = Student::factory()->create();

    Enrollment::factory()->create(['student_id' => $student->id, 'section_id' => $section->id]);

    $service = new GradeService;

    expect($service->termGpa($student, $term))->toBeNull()
        ->and($service->cumulativeGpa($student))->toBeNull();
});

test('dropped enrollments do not contribute to GPA or the transcript', function () {
    $term = AcademicTerm::factory()->current()->create();

    $section = CourseSection::factory()->create(['term_id' => $term->id]);
    $student = Student::factory()->create();

    $dropped = Enrollment::factory()->dropped()->create([
        'student_id' => $student->id,
        'section_id' => $section->id,
    ]);

    $assessment = Assessment::query()->create([
        'section_id' => $section->id, 'title' => 'Final', 'type' => 'final', 'weight' => 100, 'max_score' => 100,
    ]);
    Grade::query()->create(['assessment_id' => $assessment->id, 'enrollment_id' => $dropped->id, 'score' => 100]);

    $service = new GradeService;

    expect($service->cumulativeGpa($student))->toBeNull()
        ->and($service->transcript($student))->toBe([]);
});

test('transcript groups enrollments by term and omits dropped rows', function () {
    $term = AcademicTerm::factory()->current()->create();

    $section = CourseSection::factory()->create(['term_id' => $term->id]);
    $student = Student::factory()->create();

    $active = Enrollment::factory()->create(['student_id' => $student->id, 'section_id' => $section->id]);
    Enrollment::factory()->dropped()->create([
        'student_id' => $student->id,
        'section_id' => CourseSection::factory()->create(['term_id' => $term->id])->id,
    ]);

    $assessment = Assessment::query()->create([
        'section_id' => $section->id, 'title' => 'Final', 'type' => 'final', 'weight' => 100, 'max_score' => 100,
    ]);
    Grade::query()->create(['assessment_id' => $assessment->id, 'enrollment_id' => $active->id, 'score' => 92]);

    $transcript = (new GradeService)->transcript($student);

    expect(count($transcript))->toBe(1)
        ->and($transcript[0]['gpa'])->toBe(4.0)
        ->and(count($transcript[0]['enrollments']))->toBe(1)
        ->and($transcript[0]['enrollments'][0]['letter'])->toBe(LetterGrade::A)
        ->and($transcript[0]['enrollments'][0]['code'])->toBe($section->fullCode());
});
