<?php

use App\Enums\UserRole;
use App\Filament\Admin\Resources\Departments\DepartmentResource;
use App\Models\AcademicTerm;
use App\Models\Department;
use App\Models\Instructor;
use App\Models\Program;
use App\Models\Room;
use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

function staffUser(UserRole $role): User
{
    return User::factory()->asRole($role)->create();
}

function headUserFor(Department $department): User
{
    $user = User::factory()->asRole(UserRole::DepartmentHead)->create();

    Instructor::factory()->departmentHead()->forExistingUser($user)->create([
        'department_id' => $department->id,
    ]);

    return $user;
}

test('all academic staff can open academic resources in the panel', function (UserRole $role) {
    actingAs(staffUser($role))
        ->get(DepartmentResource::getUrl('index'))
        ->assertOk();
})->with([
    UserRole::SystemAdmin,
    UserRole::Registrar,
    UserRole::DepartmentHead,
    UserRole::FinanceOfficer,
]);

test('the dashboard is reachable for staff and blocked for students', function () {
    actingAs(staffUser(UserRole::Registrar))
        ->get('/admin')
        ->assertOk();

    actingAs(staffUser(UserRole::Student))
        ->get('/admin')
        ->assertForbidden();

    actingAs(staffUser(UserRole::Instructor))
        ->get('/admin')
        ->assertForbidden();
});

test('students and instructors can not open any academic resource', function (UserRole $role) {
    actingAs(staffUser($role))
        ->get(DepartmentResource::getUrl('index'))
        ->assertForbidden();
})->with([
    UserRole::Student,
    UserRole::Instructor,
]);

test('registrar can manage departments while finance officer is read-only', function () {
    $registrar = staffUser(UserRole::Registrar);
    $finance = staffUser(UserRole::FinanceOfficer);
    $department = Department::factory()->create();

    expect($registrar->can('create', Department::class))->toBeTrue()
        ->and($registrar->can('update', $department))->toBeTrue()
        ->and($registrar->can('delete', $department))->toBeTrue()
        ->and($finance->can('viewAny', Department::class))->toBeTrue()
        ->and($finance->can('create', Department::class))->toBeFalse()
        ->and($finance->can('update', $department))->toBeFalse()
        ->and($finance->can('delete', $department))->toBeFalse();
});

test('department heads may edit their own department only', function () {
    $ownDepartment = Department::factory()->create();
    $otherDepartment = Department::factory()->create();
    $head = headUserFor($ownDepartment);

    expect($head->can('viewAny', Department::class))->toBeTrue()
        ->and($head->can('update', $ownDepartment))->toBeTrue()
        ->and($head->can('delete', $ownDepartment))->toBeTrue()
        ->and($head->can('update', $otherDepartment))->toBeFalse()
        ->and($head->can('delete', $otherDepartment))->toBeFalse()
        ->and($head->can('create', Department::class))->toBeFalse();
});

test('department heads are limited to rooms and terms in read-only mode', function () {
    $head = headUserFor(Department::factory()->create());

    expect($head->can('viewAny', Room::class))->toBeTrue()
        ->and($head->can('create', Room::class))->toBeFalse()
        ->and($head->can('update', Room::factory()->make()))->toBeFalse()
        ->and($head->can('viewAny', AcademicTerm::class))->toBeTrue()
        ->and($head->can('update', AcademicTerm::factory()->make()))->toBeFalse();
});

test('department heads can manage only the students of their department', function () {
    $ownDepartment = Department::factory()->create();
    $otherDepartment = Department::factory()->create();
    $head = headUserFor($ownDepartment);

    $ownStudent = Student::factory()->create([
        'program_id' => Program::factory()->create(['department_id' => $ownDepartment->id]),
    ]);
    $otherStudent = Student::factory()->create([
        'program_id' => Program::factory()->create(['department_id' => $otherDepartment->id]),
    ]);

    expect($head->can('update', $ownStudent))->toBeTrue()
        ->and($head->can('delete', $ownStudent))->toBeTrue()
        ->and($head->can('update', $otherStudent))->toBeFalse()
        ->and($head->can('create', Student::class))->toBeFalse();
});

test('marks at most one academic term as current', function () {
    $first = AcademicTerm::factory()->create(['is_current' => true]);

    $second = AcademicTerm::factory()->create(['is_current' => true]);
    $first->refresh();

    expect($first->fresh()->is_current)->toBeFalse()
        ->and($second->fresh()->is_current)->toBeTrue()
        ->and(AcademicTerm::query()->where('is_current', true)->count())->toBe(1)
        ->and(AcademicTerm::query()->current()->first()?->getKey())->toBe($second->getKey());
});

test('unsetting the current term flag leaves other terms untouched', function () {
    $term = AcademicTerm::factory()->create(['is_current' => false]);

    $term->update(['name' => 'Renamed term']);

    expect($term->fresh()->name)->toBe('Renamed term')
        ->and(AcademicTerm::query()->where('is_current', true)->count())->toBe(0);
});
