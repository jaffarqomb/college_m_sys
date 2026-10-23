<?php

use App\Actions\Academic\SaveSectionScores;
use App\Models\Assessment;
use App\Models\CourseSection;
use App\Models\Enrollment;
use App\Models\Grade;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;

use function Pest\Laravel\assertDatabaseHas;

uses(RefreshDatabase::class);

/**
 * @return array{0: CourseSection, 1: Assessment, 2: Enrollment}
 */
function scoreSetup(): array
{
    $section = CourseSection::factory()->inCurrentTerm()->create();

    $assessment = Assessment::query()->create([
        'section_id' => $section->id,
        'title' => 'Quiz 1',
        'type' => 'quiz',
        'weight' => 20,
        'max_score' => 50,
    ]);

    $enrollment = Enrollment::factory()->create(['section_id' => $section->id]);

    return [$section, $assessment, $enrollment];
}

test('saving scores upserts a grade per enrollment', function () {
    [$section, $assessment, $enrollment] = scoreSetup();

    new SaveSectionScores()->handle($section, [
        $assessment->id => [$enrollment->id => 42.5],
    ]);

    assertDatabaseHas(Grade::class, [
        'assessment_id' => $assessment->id,
        'enrollment_id' => $enrollment->id,
        'score' => 42.5,
    ]);
});

test('re-saving a score overwrites the previous grade without duplicating rows', function () {
    [$section, $assessment, $enrollment] = scoreSetup();

    new SaveSectionScores()->handle($section, [$assessment->id => [$enrollment->id => 10]]);
    new SaveSectionScores()->handle($section, [$assessment->id => [$enrollment->id => 30]]);

    expect(Grade::query()->count())->toBe(1)
        ->and(Grade::query()->first()->score)->toBe('30.00');
});

test('empty or null score cells are skipped entirely', function () {
    [$section, $assessment, $enrollment] = scoreSetup();

    new SaveSectionScores()->handle($section, [
        $assessment->id => [$enrollment->id => ''],
    ]);

    expect(Grade::query()->count())->toBe(0);
});

test('scores above the assessment maximum are rejected', function () {
    [$section, $assessment, $enrollment] = scoreSetup();

    new SaveSectionScores()->handle($section, [
        $assessment->id => [$enrollment->id => 60],
    ]);
})->throws(ValidationException::class, 'Score for "Quiz 1" must be between 0 and 50.');

test('negative scores are rejected', function () {
    [$section, $assessment, $enrollment] = scoreSetup();

    new SaveSectionScores()->handle($section, [
        $assessment->id => [$enrollment->id => -5],
    ]);
})->throws(ValidationException::class);

test('non-numeric score text is rejected', function () {
    [$section, $assessment, $enrollment] = scoreSetup();

    new SaveSectionScores()->handle($section, [
        $assessment->id => [$enrollment->id => 'excellent'],
    ]);
})->throws(ValidationException::class, 'Score for assessment "Quiz 1" must be a number.');

test('scores for students of another section are rejected', function () {
    [$section, $assessment, $enrollment] = scoreSetup();

    $foreign = Enrollment::factory()->create([
        'section_id' => CourseSection::factory()->create()->id,
    ]);

    new SaveSectionScores()->handle($section, [
        $assessment->id => [$foreign->id => 10],
    ]);
})->throws(ValidationException::class, 'Scores include a student who is not enrolled in this section.');

test('assessments of another section are rejected', function () {
    [$section, $assessment, $enrollment] = scoreSetup();

    $foreignAssessment = Assessment::query()->create([
        'section_id' => CourseSection::factory()->create()->id,
        'title' => 'Foreign quiz',
        'type' => 'quiz',
        'weight' => 10,
        'max_score' => 50,
    ]);

    new SaveSectionScores()->handle($section, [
        $foreignAssessment->id => [$enrollment->id => 10],
    ]);
})->throws(ValidationException::class, 'does not belong to this section.');
