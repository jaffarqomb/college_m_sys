<?php

use App\Enums\UserRole;
use App\Models\AcademicTerm;
use App\Models\CourseSection;
use App\Models\Enrollment;
use App\Models\Instructor;
use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\assertDatabaseHas;

uses(RefreshDatabase::class);

function dashboardUrl(User $user): string
{
    // switchTeam() sets global URL::defaults (last created user wins), so
    // tests must pin the team explicitly instead of relying on defaults.
    return route('dashboard', ['current_team' => $user->currentTeam->slug]);
}

function studentUser(): User
{
    return Student::factory()->create()->user;
}

test('a student with a profile sees the portal islands on the dashboard', function () {
    $user = studentUser();

    actingAs($user)
        ->get(dashboardUrl($user))
        ->assertOk()
        ->assertSee('My enrollments')
        ->assertSee('Open sections');
});

test('a student without a profile sees a contact-registrar notice', function () {
    $user = User::factory()->asRole(UserRole::Student)->create();

    actingAs($user)
        ->get(dashboardUrl($user))
        ->assertOk()
        ->assertSee('No student profile yet');
});

test('the my-enrollments island lists active enrollments', function () {
    $user = studentUser();
    $term = currentTerm();
    $section = CourseSection::factory()->create(['term_id' => $term->id]);

    Enrollment::query()->create([
        'student_id' => $user->studentProfile->id,
        'section_id' => $section->id,
        'status' => 'active',
        'enrolled_at' => now(),
    ]);

    actingAs($user);

    Livewire::test('portal-my-enrollments')
        ->assertSee($section->course->code)
        ->assertSee('Drop');
});

test('the open-sections island lists sections and allows enrolling', function () {
    $user = studentUser();
    $term = currentTerm();
    $section = CourseSection::factory()->create(['term_id' => $term->id]);

    actingAs($user);

    Livewire::test('portal-open-sections')
        ->assertSee($section->course->code)
        ->call('enroll', $section->id)
        ->assertOk();

    assertDatabaseHas(Enrollment::class, [
        'student_id' => $user->studentProfile->id,
        'section_id' => $section->id,
        'status' => 'active',
    ]);
});

test('enrolling twice is rejected and no duplicate enrollment is kept', function () {
    $user = studentUser();
    $term = currentTerm();
    $section = CourseSection::factory()->create(['term_id' => $term->id]);

    Enrollment::query()->create([
        'student_id' => $user->studentProfile->id,
        'section_id' => $section->id,
        'status' => 'active',
        'enrolled_at' => now(),
    ]);

    actingAs($user);

    Livewire::actingAs($user)
        ->test('portal-open-sections')
        ->call('enroll', $section->id)
        ->assertSet('error', 'You are already enrolled in this section.');

    expect(DB::query()->from('enrollments')->where('section_id', $section->id)->count())->toBe(1)
        ->and($user->studentProfile->enrollments()->where('section_id', $section->id)->count())
        ->toBe(1);
});

test('a student can drop through the my-enrollments island', function () {
    $user = studentUser();
    $term = currentTerm();
    $section = CourseSection::factory()->create(['term_id' => $term->id]);

    $enrollment = Enrollment::query()->create([
        'student_id' => $user->studentProfile->id,
        'section_id' => $section->id,
        'status' => 'active',
        'enrolled_at' => now(),
    ]);

    actingAs($user);

    Livewire::test('portal-my-enrollments')
        ->call('drop', $enrollment->id)
        ->assertOk();

    assertDatabaseHas(Enrollment::class, [
        'id' => $enrollment->id,
        'status' => 'dropped',
    ]);
});

test('the instructor island only shows own sections', function () {
    $user = User::factory()->asRole(UserRole::Instructor)->create();
    $instructor = Instructor::factory()->forExistingUser($user)->create();

    $term = currentTerm();
    $ownSection = CourseSection::factory()->create(['term_id' => $term->id, 'instructor_id' => $instructor->id]);
    $otherCourseSection = CourseSection::factory()->create(['term_id' => $term->id]);

    actingAs($user)
        ->get(dashboardUrl($user))
        ->assertOk()
        ->assertSee($ownSection->course->code)
        ->assertDontSee($otherCourseSection->course->code);
});

test('staff see a link to the admin panel on the dashboard', function () {
    $user = User::factory()->asRole(UserRole::Registrar)->create();

    actingAs($user)
        ->get(dashboardUrl($user))
        ->assertOk()
        ->assertSee('Open admin panel');
});

function currentTerm(): AcademicTerm
{
    return AcademicTerm::factory()->current()->create([
        'start_date' => now()->subMonth(),
        'end_date' => now()->addMonths(2),
    ]);
}
