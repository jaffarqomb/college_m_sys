<?php

use App\Enums\LetterGrade;
use App\Enums\UserRole;
use App\Models\AcademicTerm;
use App\Models\Assessment;
use App\Models\CourseSection;
use App\Models\Enrollment;
use App\Models\Grade;
use App\Models\Instructor;
use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;

use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

function transStudent(): User
{
    return Student::factory()->create()->user;
}

function transInstructor(): array
{
    $user = User::factory()->asRole(UserRole::Instructor)->create();
    $instructor = Instructor::factory()->forExistingUser($user)->create();

    return [$user, $instructor];
}

function transContext(): array
{
    $term = AcademicTerm::factory()->current()->create([
        'start_date' => now()->subMonth(),
        'end_date' => now()->addMonths(2),
    ]);

    $section = CourseSection::factory()->create([
        'term_id' => $term->id,
    ]);

    $student = Student::factory()->create();

    $enrollment = Enrollment::factory()->create([
        'student_id' => $student->id,
        'section_id' => $section->id,
    ]);

    return [$term, $section, $student, $enrollment];
}

test('the student dashboard shows the gradebook island and transcript link', function () {
    $user = transStudent();
    transContext();

    actingAs($user)
        ->get(route('dashboard', ['current_team' => $user->currentTeam->slug]))
        ->assertOk()
        ->assertSee('My grades')
        ->assertSee('Full transcript');
});

test('the portal gradebook island lists enrollments with in-progress letters', function () {
    [$term, $section, $student, $enrollment] = transContext();
    $user = $student->user;

    actingAs($user);

    Livewire::test('portal-gradebook')
        ->assertSee($section->course->code)
        ->assertSee('In progress')
        ->assertSee('Term GPA');
});

test('the portal gradebook island shows final letters once scores exist', function () {
    [$term, $section, $student, $enrollment] = transContext();
    $user = $student->user;

    $assessment = Assessment::query()->create([
        'section_id' => $section->id, 'title' => 'Final Exam', 'type' => 'final', 'weight' => 100, 'max_score' => 100,
    ]);
    Grade::query()->create(['assessment_id' => $assessment->id, 'enrollment_id' => $enrollment->id, 'score' => 95]);

    actingAs($user);

    Livewire::test('portal-gradebook')
        ->assertSee($section->course->code)
        ->assertSee(LetterGrade::A->label())
        ->assertSee('95%');
});

test('an instructor dashboard shows the attendance and gradebook islands', function () {
    [$user] = transInstructor();
    transContext();

    actingAs($user)
        ->get(route('dashboard', ['current_team' => $user->currentTeam->slug]))
        ->assertOk()
        ->assertSee('Attendance marking')
        ->assertSee('Gradebook');
});

test('the attendance island saves a full roster through the bulk action', function () {
    [$user, $instructor] = transInstructor();
    [, $section, $student, $enrollment] = transContext();

    $section->update(['instructor_id' => $instructor->id]);

    actingAs($user);

    Livewire::test('attendance-marking')
        ->assertSee($student->displayName())
        ->call('markAll', 'absent')
        ->call('save')
        ->assertSet('error', null);

    $record = DB::table('attendance_records')->sole();

    expect(DB::table('class_sessions')->count())->toBe(1)
        ->and($record->enrollment_id)->toBe($enrollment->id)
        ->and($record->status)->toBe('absent');
});

test('the attendance island cannot mark a foreign section', function () {
    [$user] = transInstructor();
    transContext();

    $foreign = CourseSection::factory()->inCurrentTerm()->create();

    actingAs($user);

    Livewire::test('attendance-marking')
        ->set('sectionId', $foreign->id)
        ->call('save')
        ->assertSet('error', 'Choose one of your sections first.');

    expect(DB::table('attendance_records')->count())->toBe(0);
});

test('the gradebook island persists entered scores', function () {
    [$user, $instructor] = transInstructor();
    [, $section, , $enrollment] = transContext();

    $section->update(['instructor_id' => $instructor->id]);

    $assessment = Assessment::query()->create([
        'section_id' => $section->id,
        'title' => 'Quiz 1',
        'type' => 'quiz',
        'weight' => 20,
        'max_score' => 50,
    ]);

    actingAs($user);

    Livewire::test('instructor-gradebook')
        ->assertSee('Quiz 1')
        ->set("scoresByAssessment.{$assessment->id}.{$enrollment->id}", '40')
        ->call('save')
        ->assertSet('error', null);

    expect(Grade::query()->count())->toBe(1)
        ->and(Grade::query()->first()->score)->toBe('40.00');
});

test('the gradebook island rejects a score above the maximum', function () {
    [$user, $instructor] = transInstructor();
    [, $section, , $enrollment] = transContext();

    $section->update(['instructor_id' => $instructor->id]);

    $assessment = Assessment::query()->create([
        'section_id' => $section->id,
        'title' => 'Quiz 1',
        'type' => 'quiz',
        'weight' => 20,
        'max_score' => 50,
    ]);

    actingAs($user);

    Livewire::test('instructor-gradebook')
        ->set("scoresByAssessment.{$assessment->id}.{$enrollment->id}", '99')
        ->call('save')
        ->assertSet('error', 'Score for "Quiz 1" must be between 0 and 50.');

    expect(Grade::query()->count())->toBe(0);
});

test('the transcript page renders graded rows for a student', function () {
    [$term, $section, $student, $enrollment] = transContext();
    $user = $student->user;

    $assessment = Assessment::query()->create([
        'section_id' => $section->id, 'title' => 'Final Exam', 'type' => 'final', 'weight' => 100, 'max_score' => 100,
    ]);
    Grade::query()->create(['assessment_id' => $assessment->id, 'enrollment_id' => $enrollment->id, 'score' => 95]);

    actingAs($user)
        ->get(route('transcript', ['current_team' => $user->currentTeam->slug]))
        ->assertOk()
        ->assertSee('Academic transcript')
        ->assertSee($section->fullCode())
        ->assertSee(LetterGrade::A->label())
        ->assertSee('Cumulative GPA');
});

test('the transcript page is forbidden without a student profile', function () {
    [$user] = transInstructor();
    transContext();

    actingAs($user)
        ->get(route('transcript', ['current_team' => $user->currentTeam->slug]))
        ->assertForbidden();
});
