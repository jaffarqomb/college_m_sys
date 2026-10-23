<?php

use App\Actions\Academic\DropEnrollment;
use App\Models\AcademicTerm;
use App\Models\CourseSection;
use App\Models\Enrollment;
use App\Models\Student;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;

use function Pest\Laravel\assertDatabaseHas;

uses(RefreshDatabase::class);

function activeEnrollment(): Enrollment
{
    $term = AcademicTerm::factory()->current()->create([
        'start_date' => now()->subMonth(),
        'end_date' => now()->addMonths(2),
    ]);

    return Enrollment::query()->create([
        'student_id' => Student::factory()->create()->id,
        'section_id' => CourseSection::factory()->create(['term_id' => $term->id])->id,
        'status' => 'active',
        'enrolled_at' => now(),
    ]);
}

test('an active enrollment can be dropped', function () {
    $enrollment = activeEnrollment();

    $dropped = app(DropEnrollment::class)->handle($enrollment);

    expect($dropped->status)->toBe('dropped');
    expect($dropped->dropped_at)->not->toBeNull();

    assertDatabaseHas('enrollments', [
        'id' => $enrollment->id,
        'status' => 'dropped',
    ]);
});

test('a non-active enrollment cannot be dropped', function () {
    $enrollment = Enrollment::query()->create([
        'student_id' => Student::factory()->create()->id,
        'section_id' => CourseSection::factory()->inCurrentTerm()->create()->id,
        'status' => 'completed',
        'enrolled_at' => now(),
    ]);

    expect(fn () => app(DropEnrollment::class)->handle($enrollment))
        ->toThrow(ValidationException::class, 'Only active enrollments can be dropped.');
});

test('enrollments cannot be dropped after the term ends', function () {
    $term = AcademicTerm::factory()->current()->create([
        'end_date' => now()->subDay(),
    ]);

    $enrollment = Enrollment::query()->create([
        'student_id' => Student::factory()->create()->id,
        'section_id' => CourseSection::factory()->create(['term_id' => $term->id])->id,
        'status' => 'active',
        'enrolled_at' => now(),
    ]);

    expect(fn () => app(DropEnrollment::class)->handle($enrollment))
        ->toThrow(ValidationException::class);
});
