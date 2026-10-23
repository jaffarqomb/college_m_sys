<?php

use App\Actions\Timetable\PublishTimetableGeneration;
use App\Enums\TimetableGenerationStatus;
use App\Enums\UserRole;
use App\Filament\Admin\Pages\TimetableBuilder;
use App\Jobs\GenerateTimetable;
use App\Models\AcademicTerm;
use App\Models\Course;
use App\Models\CourseSection;
use App\Models\Instructor;
use App\Models\Room;
use App\Models\SectionSchedule;
use App\Models\TimetableGeneration;
use App\Models\User;
use App\Services\Timetable\TimetableGeneratorService;
use Filament\Actions\Testing\TestAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\assertDatabaseHas;
use function Pest\Laravel\assertDatabaseMissing;

uses(RefreshDatabase::class);

function ttbUser(UserRole $role): User
{
    return User::factory()->asRole($role)->create();
}

function ttbCurrentTerm(): AcademicTerm
{
    static $sequence = 0;

    $sequence++;

    return AcademicTerm::factory()->current()->create([
        'name' => 'Builder Term '.$sequence,
        'code' => 'BTR-'.sprintf('%03d', $sequence),
        'start_date' => now()->subWeek(),
        'end_date' => now()->addMonths(2),
    ]);
}

/**
 * A one-meeting weekly section placed by the solver effortlessly.
 */
function ttbLightSection(AcademicTerm $term): CourseSection
{
    static $sequence = 0;

    $sequence++;

    $instructor = Instructor::factory()->create();
    $course = Course::factory()->create([
        'code' => 'BTT-'.sprintf('%03d', $sequence),
        'credits' => 1,
    ]);

    return CourseSection::factory()->create([
        'course_id' => $course->id,
        'term_id' => $term->id,
        'instructor_id' => $instructor->id,
        'capacity' => 30,
        'requires_lab' => false,
    ]);
}

function ttbPlacement(CourseSection $section, array $slot): array
{
    return [
        'section_id' => $section->id,
        'code' => $section->fullCode(),
        'course' => $section->course->title,
        'instructor' => $section->instructor?->user->name,
        'fixed' => false,
        'slots' => [$slot],
    ];
}

function ttbDraftGeneration(AcademicTerm $term, array $assignments): TimetableGeneration
{
    return TimetableGeneration::query()->create([
        'term_id' => $term->id,
        'user_id' => ttbUser(UserRole::Registrar)->id,
        'status' => TimetableGenerationStatus::Draft,
        'settings' => TimetableGeneratorService::defaultSettings(),
        'assignments' => $assignments,
        'conflicts' => [],
    ]);
}

test('all panel staff can open the ai timetable page', function (UserRole $role) {
    actingAs(ttbUser($role))
        ->get(TimetableBuilder::getUrl())
        ->assertOk();
})->with([
    UserRole::SystemAdmin,
    UserRole::Registrar,
    UserRole::DepartmentHead,
    UserRole::FinanceOfficer,
]);

test('students and instructors can not open the ai timetable page', function (UserRole $role) {
    actingAs(ttbUser($role))
        ->get(TimetableBuilder::getUrl())
        ->assertForbidden();
})->with([
    UserRole::Student,
    UserRole::Instructor,
]);

test('the generate action queues the solver job and creates a generating draft', function () {
    Queue::fake();

    actingAs(ttbUser(UserRole::Registrar));

    $term = ttbCurrentTerm();

    Livewire::test(TimetableBuilder::class)
        ->callAction('generate', data: [
            'term_id' => $term->id,
            'days' => [1, 2, 3, 4, 5],
            'day_start' => '08:00',
            'day_end' => '18:00',
            'granularity' => '30',
            'max_daily_hours' => '5',
            'buffer_minutes' => '15',
            'regenerate_existing' => false,
        ])
        ->assertHasNoActionErrors();

    $generation = TimetableGeneration::query()->sole();

    expect($generation->status)->toBe(TimetableGenerationStatus::Generating);
    expect($generation->term_id)->toBe($term->id);
    expect($generation->assignments)->toBe([]);

    Queue::assertPushedOn('timetable', GenerateTimetable::class);
    Queue::assertPushed(GenerateTimetable::class, fn (GenerateTimetable $job): bool => $job->generation->is($generation));
});

test('only system admins and registrars can generate drafts', function (UserRole $role) {
    actingAs(ttbUser($role));

    Livewire::test(TimetableBuilder::class)
        ->assertActionHidden('generate');
})->with([
    UserRole::DepartmentHead,
    UserRole::FinanceOfficer,
]);

test('the solver job turns a pending generation into a draft', function () {
    $term = ttbCurrentTerm();

    Room::factory()->create(['capacity' => 50]);

    ttbLightSection($term);
    ttbLightSection($term);

    $generation = TimetableGeneration::query()->create([
        'term_id' => $term->id,
        'user_id' => ttbUser(UserRole::Registrar)->id,
        'status' => TimetableGenerationStatus::Generating,
        'settings' => TimetableGeneratorService::defaultSettings(),
        'assignments' => [],
        'conflicts' => [],
    ]);

    (new GenerateTimetable($generation))->handle(new TimetableGeneratorService);

    $generation->refresh();

    expect($generation->status)->toBe(TimetableGenerationStatus::Draft);
    expect(count($generation->assignments))->toBe(2);
    expect($generation->fitness)->not->toBeNull();
    expect($generation->conflicts)->toBe([]);

    foreach ($generation->assignments as $assignment) {
        expect($assignment['slots'])->not->toBeEmpty();

        foreach ($assignment['slots'] as $slot) {
            expect($slot['room_id'])->not->toBeNull();
        }
    }
});

test('publishing replaces schedules of drafted sections and leaves conflicted sections alone', function () {
    $term = ttbCurrentTerm();
    $oldRoom = Room::factory()->create(['capacity' => 40]);
    $newRoom = Room::factory()->create(['capacity' => 30]);

    $moved = ttbLightSection($term);
    $untouched = ttbLightSection($term);

    $moved->sectionSchedules()->create([
        'room_id' => $oldRoom->id,
        'day_of_week' => 1,
        'start_time' => '09:00',
        'end_time' => '10:00',
    ]);

    $untouched->sectionSchedules()->create([
        'room_id' => $oldRoom->id,
        'day_of_week' => 2,
        'start_time' => '11:00',
        'end_time' => '12:00',
    ]);

    $generation = ttbDraftGeneration($term, [
        ttbPlacement($moved, ['day' => 3, 'start' => '14:00', 'end' => '15:00', 'room_id' => $newRoom->id]),
    ]);

    app(PublishTimetableGeneration::class)->handle($generation);

    expect($generation->refresh()->status)->toBe(TimetableGenerationStatus::Published);
    expect($generation->published_at)->not->toBeNull();

    // Drafted section: old slot replaced by the new one.
    assertDatabaseHas(SectionSchedule::class, [
        'section_id' => $moved->id,
        'room_id' => $newRoom->id,
        'day_of_week' => 3,
        'start_time' => '14:00',
        'end_time' => '15:00',
    ]);

    assertDatabaseMissing(SectionSchedule::class, [
        'section_id' => $moved->id,
        'room_id' => $oldRoom->id,
        'day_of_week' => 1,
    ]);

    // Conflicted section keeps its original schedule.
    assertDatabaseHas(SectionSchedule::class, [
        'section_id' => $untouched->id,
        'room_id' => $oldRoom->id,
        'day_of_week' => 2,
        'start_time' => '11:00',
        'end_time' => '12:00',
    ]);
});

test('only draft generations can be published', function () {
    $term = ttbCurrentTerm();

    $generation = TimetableGeneration::query()->create([
        'term_id' => $term->id,
        'user_id' => ttbUser(UserRole::Registrar)->id,
        'status' => TimetableGenerationStatus::Generating,
        'settings' => [],
        'assignments' => [],
        'conflicts' => [],
    ]);

    $publish = fn (): array => app(PublishTimetableGeneration::class)->handle($generation);

    expect($publish)->toThrow(ValidationException::class);

    $generation->update(['status' => TimetableGenerationStatus::Published]);

    expect($publish)->toThrow(ValidationException::class);
});

test('adjusting a section into a busy slot is rejected and leaves the draft untouched', function () {
    $term = ttbCurrentTerm();
    $taken = Room::factory()->create(['capacity' => 40]);
    $free = Room::factory()->create(['capacity' => 30]);

    $sectionA = ttbLightSection($term);
    $sectionB = ttbLightSection($term);

    $generation = ttbDraftGeneration($term, [
        [...ttbPlacement($sectionA, ['day' => 1, 'start' => '10:00', 'end' => '11:00', 'room_id' => $taken->id]), 'fixed' => true],
        ttbPlacement($sectionB, ['day' => 2, 'start' => '10:00', 'end' => '11:00', 'room_id' => $taken->id]),
    ]);

    actingAs(ttbUser(UserRole::Registrar));

    Livewire::test(TimetableBuilder::class)
        ->callAction(TestAction::make('adjust')->table($sectionB), data: [
            'day' => '1',
            'start' => '10:00',
            'end' => '11:00',
            'room_id' => $taken->id,
        ])
        ->assertNotified();

    $generation->refresh();

    $slots = $generation->assignments[1]['slots'];
    expect($slots[0]['day'])->toBe(2);
    expect($slots[0]['start'])->toBe('10:00');
    expect((int) $slots[0]['room_id'])->toBe($taken->id);
});

test('adjusting a section into a free slot updates the draft', function () {
    $term = ttbCurrentTerm();
    $room = Room::factory()->create(['capacity' => 40]);

    $sectionA = ttbLightSection($term);
    $sectionB = ttbLightSection($term);

    $generation = ttbDraftGeneration($term, [
        ttbPlacement($sectionA, ['day' => 1, 'start' => '10:00', 'end' => '11:00', 'room_id' => $room->id]),
        ttbPlacement($sectionB, ['day' => 2, 'start' => '10:00', 'end' => '11:00', 'room_id' => $room->id]),
    ]);

    actingAs(ttbUser(UserRole::Registrar));

    Livewire::test(TimetableBuilder::class)
        ->callAction(TestAction::make('adjust')->table($sectionB), data: [
            'day' => '3',
            'start' => '13:00',
            'end' => '14:00',
            'room_id' => $room->id,
        ]);

    $generation->refresh();
    $slots = $generation->assignments[1]['slots'];

    expect($slots[0]['day'])->toBe(3);
    expect($slots[0]['start'])->toBe('13:00');
    expect($slots[0]['end'])->toBe('14:00');
    expect($generation->assignments[1]['fixed'])->toBeFalse();
});

test('the ai review action displays the local model critique on the page', function () {
    Http::fake([
        '*/api/tags' => Http::response([], 200),
        '*/api/chat' => Http::response([
            'message' => ['content' => '- Compact BTT-001 into the morning block for better utilization'],
        ]),
    ]);

    $term = ttbCurrentTerm();
    $room = Room::factory()->create(['capacity' => 40]);
    $section = ttbLightSection($term);

    ttbDraftGeneration($term, [
        ttbPlacement($section, ['day' => 1, 'start' => '10:00', 'end' => '11:00', 'room_id' => $room->id]),
    ]);

    actingAs(ttbUser(UserRole::Registrar));

    Livewire::test(TimetableBuilder::class)
        ->callAction('aiReview')
        ->assertHasNoActionErrors()
        ->assertSet('aiAnalysis', '- Compact BTT-001 into the morning block for better utilization')
        ->assertSet('aiAnalysisKind', 'review')
        ->assertSeeText('AI draft review')
        ->assertSeeText('Compact BTT-001 into the morning block for better utilization');
});

test('the ai conflict-fixes action reports suggestions for unplaced sections', function () {
    Http::fake([
        '*/api/tags' => Http::response([], 200),
        '*/api/chat' => Http::response([
            'message' => ['content' => '- Add a lab room or widen the day window for the blocked section'],
        ]),
    ]);

    $term = ttbCurrentTerm();

    // No rooms at all, so the lab-requiring section cannot be placed.
    $section = CourseSection::factory()->requiresLab()->create(['term_id' => $term->id]);
    $section->refresh();

    $generation = ttbDraftGeneration($term, [
        ttbPlacement($section, []),
    ]);
    $generation->update(['assignments' => [], 'conflicts' => [
        ['section_id' => $section->id, 'section' => $section->fullCode(), 'course' => $section->course->title, 'reason' => 'No suitable room.'],
    ]]);

    actingAs(ttbUser(UserRole::Registrar));

    Livewire::test(TimetableBuilder::class)
        ->callAction('aiConflictFixes')
        ->assertHasNoActionErrors()
        ->assertSet('aiAnalysis', '- Add a lab room or widen the day window for the blocked section')
        ->assertSet('aiAnalysisKind', 'conflicts');
});

test('ai actions are hidden without a draft and for states that cannot use them', function () {
    $term = ttbCurrentTerm();
    $room = Room::factory()->create(['capacity' => 40]);

    // Published generation — no AI advisory display.
    $published = ttbDraftGeneration($term, []);
    $published->update(['assignments' => [], 'status' => TimetableGenerationStatus::Published]);

    actingAs(ttbUser(UserRole::Registrar));

    Livewire::test(TimetableBuilder::class)
        ->assertActionHidden('aiReview')
        ->assertActionHidden('aiConflictFixes');

    // Draft without conflicts hides the conflict-fix action only.
    ttbDraftGeneration($term, []);

    Livewire::test(TimetableBuilder::class)
        ->assertActionVisible('aiReview')
        ->assertActionHidden('aiConflictFixes');
});

test('ai actions degrade to a warning when ollama is unreachable', function () {
    Http::fake(['*/api/tags' => Http::response([], 503)]);

    $term = ttbCurrentTerm();
    $section = ttbLightSection($term);

    ttbDraftGeneration($term, [
        ttbPlacement($section, ['day' => 1, 'start' => '10:00', 'end' => '11:00', 'room_id' => Room::factory()->create()->id]),
    ]);

    actingAs(ttbUser(UserRole::Registrar));

    Livewire::test(TimetableBuilder::class)
        ->callAction('aiReview')
        ->assertHasNoActionErrors()
        ->assertSet('aiAnalysis', null);
});
