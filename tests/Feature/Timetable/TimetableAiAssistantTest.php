<?php

use App\Models\AcademicTerm;
use App\Models\CourseSection;
use App\Models\Room;
use App\Services\OllamaService;
use App\Services\Timetable\TimetableAiAssistant;
use App\Services\Timetable\TimetableGeneratorService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

beforeEach(function () {
    config()->set('services.ollama.url', 'http://127.0.0.1:11434');
    config()->set('services.ollama.model', 'llama3.2');
});

function aiTermWithConflict(): array
{
    $term = AcademicTerm::factory()->current()->create();

    // A lab section with no lab rooms can never be placed by the solver.
    $section = CourseSection::factory()
        ->requiresLab()
        ->create(['term_id' => $term->id, 'capacity' => 20]);
    $section->refresh();

    $conflicts = [[
        'section_id' => $section->id,
        'section' => $section->fullCode(),
        'course' => $section->course->title,
        'reason' => 'No lab room can host it within the day window.',
    ]];

    return [$term, $section, $conflicts];
}

test('the ollama service reports availability through the tags endpoint', function () {
    Http::fake(['*/api/tags' => Http::response([], 200)]);

    expect(app(OllamaService::class)->isAvailable())->toBeTrue();
});

test('the ollama service reports unavailability on a failing tags endpoint', function () {
    Http::fake(['*/api/tags' => Http::response([], 503)]);

    expect(app(OllamaService::class)->isAvailable())->toBeFalse();
});

test('the draft review asks the local model and reports its reply', function () {
    $term = AcademicTerm::factory()->current()->create();
    $room = Room::factory()->create(['code' => 'AI-100', 'capacity' => 30, 'type' => 'lecture']);

    Http::fake([
        '*/api/tags' => Http::response([], 200),
        '*/api/chat' => Http::response([
            'message' => ['content' => '- Move course BTT-001 later to free AI-100 mornings'],
        ]),
    ]);

    $result = app(TimetableGeneratorService::class)->generate($term);

    $analysis = app(TimetableAiAssistant::class)->reviewDraft($term, $result, TimetableGeneratorService::defaultSettings());

    expect($analysis)->toContain('free AI-100 mornings');

    Http::assertSent(function ($request): bool {
        $body = $request->data();

        return str_ends_with($request->url(), '/api/chat')
            && $body['model'] === 'llama3.2'
            && str_contains($body['messages'][0]['content'], 'AI-100 (30 seats, lecture)');
    });
});

test('conflict-fix suggestions include the blocked section details', function () {
    [$term, $section, $conflicts] = aiTermWithConflict();

    Http::fake([
        '*/api/tags' => Http::response([], 200),
        '*/api/chat' => Http::response([
            'message' => ['content' => "- {$section->fullCode()}: schedule outside the day window or add a lab room"],
        ]),
    ]);

    $suggestions = app(TimetableAiAssistant::class)->suggestConflictFixes($term, $conflicts, TimetableGeneratorService::defaultSettings());

    expect($suggestions)->toContain($section->fullCode());

    Http::assertSent(function ($request) use ($section): bool {
        $content = (string) ($request->data()['messages'][0]['content'] ?? '');

        return str_ends_with($request->url(), '/api/chat')
            && str_contains($content, $section->fullCode())
            && str_contains($content, 'requires a lab room')
            && str_contains($content, 'Blocker');
    });
});

test('the assistant fails gracefully when ollama is unreachable', function () {
    Http::fake(['*/api/tags' => Http::response([], 503)]);

    expect(fn () => app(TimetableAiAssistant::class)->suggestConflictFixes(
        AcademicTerm::factory()->current()->create(),
        [],
        TimetableGeneratorService::defaultSettings(),
    ))->toThrow(RuntimeException::class, 'The AI assistant is unavailable');
});

test('a failing chat call surfaces its http status', function () {
    Http::fake([
        '*/api/tags' => Http::response([], 200),
        '*/api/chat' => Http::response([], 500),
    ]);

    expect(fn () => app(TimetableAiAssistant::class)->suggestConflictFixes(
        AcademicTerm::factory()->current()->create(),
        [],
        TimetableGeneratorService::defaultSettings(),
    ))->toThrow(RuntimeException::class, 'HTTP 500');
});

test('an empty model reply is rejected with a clear error', function () {
    Http::fake([
        '*/api/tags' => Http::response([], 200),
        '*/api/chat' => Http::response(['message' => ['content' => '']], 200),
    ]);

    expect(fn () => app(TimetableAiAssistant::class)->suggestConflictFixes(
        AcademicTerm::factory()->current()->create(),
        [],
        TimetableGeneratorService::defaultSettings(),
    ))->toThrow(RuntimeException::class, 'Ollama returned an invalid response.');
});
