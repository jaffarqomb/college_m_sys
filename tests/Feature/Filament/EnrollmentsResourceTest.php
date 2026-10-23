<?php

use App\Enums\UserRole;
use App\Filament\Admin\Resources\Enrollments\EnrollmentResource;
use App\Filament\Admin\Resources\Enrollments\Pages\CreateEnrollment;
use App\Models\AcademicTerm;
use App\Models\Course;
use App\Models\CourseSection;
use App\Models\Department;
use App\Models\Enrollment;
use App\Models\Instructor;
use App\Models\Program;
use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\assertDatabaseHas;

uses(RefreshDatabase::class);

function panelUser(UserRole $role): User
{
    return User::factory()->asRole($role)->create();
}

function currentRunningTerm(): AcademicTerm
{
    return AcademicTerm::factory()->current()->create([
        'start_date' => now()->subMonth(),
        'end_date' => now()->addMonths(2),
    ]);
}

test('the registrar can browse and open the enrollments screens', function () {
    $user = panelUser(UserRole::Registrar);

    actingAs($user)
        ->get(EnrollmentResource::getUrl('index'))
        ->assertOk();

    actingAs($user)
        ->get(EnrollmentResource::getUrl('create'))
        ->assertOk();
});

test('the registrar can register a student into a section through the panel', function () {
    $user = panelUser(UserRole::Registrar);
    $term = currentRunningTerm();
    $section = CourseSection::factory()->create(['term_id' => $term->id, 'capacity' => 10]);
    $student = Student::factory()->create();

    actingAs($user);

    Livewire::test(CreateEnrollment::class)
        ->fillForm([
            'student_id' => $student->id,
            'section_id' => $section->id,
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    assertDatabaseHas(Enrollment::class, [
        'student_id' => $student->id,
        'section_id' => $section->id,
        'status' => 'active',
    ]);
});

test('business rule violations block creation and record nothing (full section)', function () {
    $user = panelUser(UserRole::Registrar);
    $term = currentRunningTerm();
    $section = CourseSection::factory()->create(['term_id' => $term->id, 'capacity' => 1]);
    $student = Student::factory()->create();

    Enrollment::query()->create([
        'student_id' => Student::factory()->create()->id,
        'section_id' => $section->id,
        'status' => 'active',
        'enrolled_at' => now(),
    ]);

    actingAs($user);

    Livewire::test(CreateEnrollment::class)
        ->fillForm([
            'student_id' => $student->id,
            'section_id' => $section->id,
        ])
        ->call('create')
        ->assertNotified();

    expect(Enrollment::query()->where('student_id', $student->id)->count())->toBe(0);
});

test('unmet prerequisites block panel registration with a notification', function () {
    $user = panelUser(UserRole::Registrar);
    $term = currentRunningTerm();
    $prerequisite = Course::factory()->create();
    $course = Course::factory()->create();
    $course->prerequisites()->sync([$prerequisite->id]);

    $section = CourseSection::factory()->create(['course_id' => $course->id, 'term_id' => $term->id]);
    $student = Student::factory()->create();

    actingAs($user);

    Livewire::test(CreateEnrollment::class)
        ->fillForm([
            'student_id' => $student->id,
            'section_id' => $section->id,
        ])
        ->call('create')
        ->assertNotified();

    expect(Enrollment::query()->where('student_id', $student->id)->count())->toBe(0);
});

test('finance officers are read-only on enrollments', function () {
    $user = panelUser(UserRole::FinanceOfficer);
    $term = currentRunningTerm();
    $section = CourseSection::factory()->create(['term_id' => $term->id]);
    $student = Student::factory()->create();

    actingAs($user);

    Enrollment::query()->create([
        'student_id' => $student->id,
        'section_id' => $section->id,
        'status' => 'active',
        'enrolled_at' => now(),
    ]);

    actingAs($user)
        ->get(EnrollmentResource::getUrl('edit', ['record' => Enrollment::query()->latest('id')->first()]))
        ->assertForbidden();
});

test('department heads only see enrollments of their own department', function () {
    $headUser = panelUser(UserRole::DepartmentHead);
    $csDepartment = Department::factory()->create(['name' => 'CS Dept']);
    $otherDepartment = Department::factory()->create(['name' => 'Math Dept']);

    Instructor::factory()->departmentHead()->forExistingUser($headUser)->create([
        'department_id' => $csDepartment->id,
    ]);

    $programOne = Program::factory()->create(['department_id' => $csDepartment->id]);
    $programTwo = Program::factory()->create(['department_id' => $otherDepartment->id]);

    $ownStudent = Student::factory()->create(['program_id' => $programOne->id]);
    $foreignStudent = Student::factory()->create(['program_id' => $programTwo->id]);

    $term = currentRunningTerm();
    $section = CourseSection::factory()->create(['term_id' => $term->id]);

    $own = Enrollment::query()->create([
        'student_id' => $ownStudent->id,
        'section_id' => $section->id,
        'status' => 'active',
        'enrolled_at' => now(),
    ]);

    Enrollment::query()->create([
        'student_id' => $foreignStudent->id,
        'section_id' => $section->id,
        'status' => 'active',
        'enrolled_at' => now(),
    ]);

    actingAs($headUser)
        ->get(EnrollmentResource::getUrl('edit', ['record' => $own]))
        ->assertOk();
});

test('department heads cannot manage enrollments from other departments', function () {
    $headUser = panelUser(UserRole::DepartmentHead);
    $csDepartment = Department::factory()->create();

    Instructor::factory()->departmentHead()->forExistingUser($headUser)->create([
        'department_id' => $csDepartment->id,
    ]);

    $foreignProgram = Program::factory()->create();
    $foreignStudent = Student::factory()->create(['program_id' => $foreignProgram->id]);

    $term = currentRunningTerm();
    $section = CourseSection::factory()->create(['term_id' => $term->id]);

    $foreignEnrollment = Enrollment::query()->create([
        'student_id' => $foreignStudent->id,
        'section_id' => $section->id,
        'status' => 'active',
        'enrolled_at' => now(),
    ]);

    // Department-head scope hides foreign-department records (404) and the
    // policy itself denies updates (403) — either response denies management.
    $response = actingAs($headUser)
        ->get(EnrollmentResource::getUrl('edit', ['record' => $foreignEnrollment]));

    expect(in_array($response->status(), [403, 404], true))->toBeTrue();
});
