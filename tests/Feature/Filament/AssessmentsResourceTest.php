<?php

use App\Enums\UserRole;
use App\Filament\Admin\Resources\Assessments\AssessmentsResource;
use App\Filament\Admin\Resources\Assessments\Pages\CreateAssessment;
use App\Models\AcademicTerm;
use App\Models\Assessment;
use App\Models\CourseSection;
use App\Models\Department;
use App\Models\Instructor;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\assertDatabaseHas;

uses(RefreshDatabase::class);

function astPanelUser(UserRole $role): User
{
    return User::factory()->asRole($role)->create();
}

test('the registrar can browse and open the assessments screens', function () {
    $user = astPanelUser(UserRole::Registrar);

    actingAs($user)
        ->get(AssessmentsResource::getUrl('index'))
        ->assertOk();

    actingAs($user)
        ->get(AssessmentsResource::getUrl('create'))
        ->assertOk();
});

test('the registrar creates an assessment through the panel', function () {
    $user = astPanelUser(UserRole::Registrar);

    $term = AcademicTerm::factory()->current()->create();
    $section = CourseSection::factory()->create(['term_id' => $term->id, 'capacity' => 10]);

    actingAs($user);

    Livewire::test(CreateAssessment::class)
        ->fillForm([
            'section_id' => $section->id,
            'title' => 'Lab Report 1',
            'type' => 'lab',
            'weight' => 15,
            'max_score' => 30,
        ])
        ->call('create')
        ->assertHasNoErrors();

    assertDatabaseHas(Assessment::class, [
        'section_id' => $section->id,
        'title' => 'Lab Report 1',
        'type' => 'lab',
        'weight' => 15,
        'max_score' => 30,
    ]);
});

test('instructors cannot reach the assessments screen through the panel', function () {
    // Instructors do not have panel access at all; only academic staff do.
    $user = astPanelUser(UserRole::Instructor);

    actingAs($user)
        ->get(AssessmentsResource::getUrl('index'))
        ->assertForbidden();
});

test('department heads only see assessments of their own department', function () {
    $user = astPanelUser(UserRole::DepartmentHead);

    $ownDepartment = Department::factory()->create();
    $otherDepartment = Department::factory()->create();

    Instructor::query()->create([
        'user_id' => $user->id,
        'department_id' => $ownDepartment->id,
        'employee_number' => 'EMP-TEST-01',
        'is_head' => true,
    ]);

    $term = AcademicTerm::factory()->current()->create();

    $own = CourseSection::factory()->create([
        'term_id' => $term->id,
        'instructor_id' => Instructor::query()->where('user_id', $user->id)->first()->id,
    ]);
    $other = CourseSection::factory()->create(['term_id' => $term->id]);

    $own->course->update(['department_id' => $ownDepartment->id]);
    $other->course->update(['department_id' => $otherDepartment->id]);

    Assessment::query()->create([
        'section_id' => $own->id, 'title' => 'Own quiz', 'type' => 'quiz', 'weight' => 10, 'max_score' => 100,
    ]);
    Assessment::query()->create([
        'section_id' => $other->id, 'title' => 'Other quiz', 'type' => 'quiz', 'weight' => 10, 'max_score' => 100,
    ]);

    actingAs($user)
        ->get(AssessmentsResource::getUrl('index'))
        ->assertOk()
        ->assertSee('Own quiz')
        ->assertDontSee('Other quiz');
});
