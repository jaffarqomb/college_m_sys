<?php

use App\Enums\UserRole;
use App\Filament\Admin\Resources\Instructors\Pages\ListInstructors;
use App\Models\AcademicTerm;
use App\Models\Course;
use App\Models\CourseSection;
use App\Models\Instructor;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

beforeEach(function () {
    actingAs(User::factory()->asRole(UserRole::Registrar)->create());
});

test('the instructors table shows the number of assigned course sections', function () {
    $instructor = Instructor::factory()->create();

    $course = Course::factory()->create(['department_id' => $instructor->department_id]);
    $term = AcademicTerm::factory()->create();

    CourseSection::factory()->sequence(['section_number' => '01'], ['section_number' => '02'])->count(2)->create([
        'instructor_id' => $instructor->id,
        'course_id' => $course->id,
        'term_id' => $term->id,
    ]);

    // The count attribute is stored snake_case ("course_sections_count");
    // the column must address that exact key. (Laravel 13 no longer folds
    // camelCase attribute lookups onto snake_case storage.)
    Livewire::test(ListInstructors::class)
        ->loadTable()
        ->assertCanSeeTableRecords([$instructor])
        ->assertTableColumnStateSet('course_sections_count', 2, $instructor);
});

test('sections count is zero for an instructor without sections', function () {
    $instructor = Instructor::factory()->create();

    Livewire::test(ListInstructors::class)
        ->loadTable()
        ->assertCanSeeTableRecords([$instructor])
        ->assertTableColumnStateSet('course_sections_count', 0, $instructor);
});
