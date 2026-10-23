<?php

use App\Enums\UserRole;
use App\Filament\Admin\Resources\Students\Pages\EditStudent;
use App\Filament\Admin\Resources\Students\RelationManagers\EnrollmentsRelationManager;
use App\Filament\Admin\Resources\Students\StudentResource;
use App\Models\AcademicTerm;
use App\Models\Course;
use App\Models\CourseSection;
use App\Models\Enrollment;
use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\SendQueuedNotifications;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;

use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

function registrar(): User
{
    return User::factory()->asRole(UserRole::Registrar)->create();
}

function runningTerm(): AcademicTerm
{
    return AcademicTerm::factory()->current()->create([
        'start_date' => now()->subMonth(),
        'end_date' => now()->addMonths(2),
    ]);
}

test('the registrar sees the student enrollments relation on the edit page', function () {
    $user = registrar();
    $term = runningTerm();
    $section = CourseSection::factory()->create(['term_id' => $term->id]);
    $student = Student::factory()->create();

    $enrollment = Enrollment::query()->create([
        'student_id' => $student->id,
        'section_id' => $section->id,
        'status' => 'active',
        'enrolled_at' => now(),
    ]);

    actingAs($user)
        ->get(StudentResource::getUrl('edit', ['record' => $student]))
        ->assertOk();

    Livewire::test(EnrollmentsRelationManager::class, [
        'ownerRecord' => $student,
        'pageClass' => EditStudent::class,
    ])->loadTable()
        ->assertSuccessful()
        ->assertCanSeeTableRecords([$enrollment]);
});

test('the registrar can register the student into a section from the edit page', function () {
    $user = registrar();
    $term = runningTerm();
    $section = CourseSection::factory()->create(['term_id' => $term->id, 'capacity' => 10]);
    $student = Student::factory()->create();

    actingAs($user);

    Livewire::test(EnrollmentsRelationManager::class, [
        'ownerRecord' => $student,
        'pageClass' => EditStudent::class,
    ])->callTableAction('create', data: ['section_id' => $section->id])
        ->assertHasNoTableActionErrors();

    expect(Enrollment::query()
        ->where('student_id', $student->id)
        ->where('section_id', $section->id)
        ->where('status', 'active')
        ->exists())->toBeTrue();
});

test('duplicate registration is blocked from the student edit page', function () {
    $user = registrar();
    $term = runningTerm();
    $section = CourseSection::factory()->create(['term_id' => $term->id]);
    $student = Student::factory()->create();

    Enrollment::query()->create([
        'student_id' => $student->id,
        'section_id' => $section->id,
        'status' => 'active',
        'enrolled_at' => now(),
    ]);

    actingAs($user);

    Livewire::test(EnrollmentsRelationManager::class, [
        'ownerRecord' => $student,
        'pageClass' => EditStudent::class,
    ])->callTableAction('create', data: ['section_id' => $section->id])
        ->assertNotified();

    expect(Enrollment::query()
        ->where('student_id', $student->id)
        ->where('section_id', $section->id)
        ->count())->toBe(1);
});

test('closed sections cannot be registered from the student edit page', function () {
    $user = registrar();
    $term = runningTerm();
    $section = CourseSection::factory()->create(['term_id' => $term->id, 'status' => 'closed']);
    $student = Student::factory()->create();

    actingAs($user);

    Livewire::test(EnrollmentsRelationManager::class, [
        'ownerRecord' => $student,
        'pageClass' => EditStudent::class,
    ])->callTableAction('create', data: ['section_id' => $section->id])
        ->assertNotified();

    expect(Enrollment::query()->where('student_id', $student->id)->exists())->toBeFalse();
});

test('unmet prerequisites are enforced from the student edit page', function () {
    $user = registrar();
    $term = runningTerm();

    $prerequisite = Course::factory()->create();
    $course = Course::factory()->create();
    $course->prerequisites()->sync([$prerequisite->id]);

    $section = CourseSection::factory()->create(['course_id' => $course->id, 'term_id' => $term->id]);
    $student = Student::factory()->create();

    actingAs($user);

    Livewire::test(EnrollmentsRelationManager::class, [
        'ownerRecord' => $student,
        'pageClass' => EditStudent::class,
    ])->callTableAction('create', data: ['section_id' => $section->id])
        ->assertNotified();

    expect(Enrollment::query()->where('student_id', $student->id)->exists())->toBeFalse();
});

test('the relation manager creates no background jobs (beyond the confirmation notification)', function () {
    Queue::fake();

    $user = registrar();
    $term = runningTerm();
    $section = CourseSection::factory()->create(['term_id' => $term->id]);
    $student = Student::factory()->create();

    actingAs($user);

    Livewire::test(EnrollmentsRelationManager::class, [
        'ownerRecord' => $student,
        'pageClass' => EditStudent::class,
    ])->callTableAction('create', data: ['section_id' => $section->id]);

    // The enrollment dispatches only the push of the queued confirmation
    // notification (one job per channel group) and nothing else.
    expect(array_keys(Queue::pushedJobs()))->toBe([SendQueuedNotifications::class]);
});
