<?php

use App\Actions\Academic\EnrollInSection;
use App\Models\AcademicTerm;
use App\Models\Course;
use App\Models\CourseSection;
use App\Models\Department;
use App\Models\Instructor;
use App\Models\Program;
use App\Models\Room;
use App\Models\SectionSchedule;
use App\Models\Student;
use App\Services\Timetable\TimetableGeneratorService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function solver(): TimetableGeneratorService
{
    return new TimetableGeneratorService;
}

function engineTerm(): AcademicTerm
{
    static $sequence = 0;

    $sequence++;

    return AcademicTerm::factory()->current()->create([
        'name' => 'Engine Term '.$sequence,
        'code' => 'ENG-'.sprintf('%03d', $sequence),
        'start_date' => now()->subMonth(),
        'end_date' => now()->addMonths(2),
    ]);
}

function sharedDepartment(): Department
{
    $existing = Department::query()->where('code', 'TTD')->first();

    if ($existing !== null) {
        return $existing;
    }

    return Department::factory()->create([
        'name' => 'Department of Timetable Testing',
        'code' => 'TTD',
    ]);
}

function cohort(string $name): Program
{
    static $sequence = 0;

    $sequence++;

    return Program::factory()->for(sharedDepartment(), 'department')->create([
        'name' => $name,
        'code' => 'BS-'.sprintf('%03d', $sequence),
    ]);
}

function freshInstructor(): Instructor
{
    return Instructor::factory()->for(sharedDepartment(), 'department')->create();
}

/**
 * A lecture section that needs exactly one 60-minute meeting per week.
 */
function oneShotSection(AcademicTerm $term, array $attributes = []): CourseSection
{
    static $sequence = 0;

    $sequence++;

    $course = Course::factory()->for(sharedDepartment(), 'department')->create([
        'code' => 'CRS-'.sprintf('%03d', $sequence),
        'credits' => 1,
    ]);

    return CourseSection::factory()->create([
        'course_id' => $course->id,
        'term_id' => $term->id,
        'capacity' => 30,
        'requires_lab' => false,
        ...$attributes,
    ]);
}

function nextRoomCode(): string
{
    static $sequence = 0;

    return 'R-Test-'.sprintf('%03d', ++$sequence);
}

function lectureRoom(int $capacity = 30): Room
{
    return Room::factory()->create(['code' => nextRoomCode(), 'capacity' => $capacity]);
}

function labRoom(int $capacity = 30): Room
{
    return Room::factory()->lab()->create(['code' => nextRoomCode(), 'capacity' => $capacity]);
}

function enrollIn(Student $student, CourseSection $section): void
{
    app(EnrollInSection::class)->handle($student, $section);
}

/**
 * Minute-of-day conversion for the clock strings inside drafts.
 */
function clockMinutes(string $clock): int
{
    [$hours, $minutes] = sscanf($clock, '%d:%d');

    return ($hours ?? 0) * 60 + ($minutes ?? 0);
}

/**
 * @param  array{assignments: list<array<string, mixed>>, conflicts: list<array<string, mixed>>, fitness: float}  $result
 * @return array<string, mixed>
 */
function placementFor(array $result, string $code): array
{
    foreach ($result['assignments'] as $assignment) {
        if (str_starts_with((string) $assignment['code'], $code)) {
            return $assignment;
        }
    }

    return [];
}

/**
 * @param  array{assignments: list<array<string, mixed>>, conflicts: list<array<string, mixed>>, fitness: float}  $result
 * @return array{section_id: int, section: string, course: string, reason: string}|null
 */
function conflictFor(array $result, string $code): ?array
{
    foreach ($result['conflicts'] as $conflict) {
        if ($conflict['section'] === $code) {
            return $conflict;
        }
    }

    return null;
}

test('sections are never double-booked in the same room', function () {
    $term = engineTerm();
    $rooms = collect([lectureRoom(30), lectureRoom(40)]);

    foreach (range(1, 4) as $i) { // four 30-seat sections, two room options
        oneShotSection($term);
    }

    $result = solver()->generate($term);

    expect($result['conflicts'])->toBeEmpty()
        ->and(count($result['assignments']))->toBe(4);

    $occupied = [];

    foreach ($result['assignments'] as $placement) {
        foreach ($placement['slots'] as $slot) {
            expect($slot['room_id'])->not->toBeNull()
                ->and($rooms->contains('id', $slot['room_id']))->toBeTrue();

            $busy = [clockMinutes($slot['start']), clockMinutes($slot['end'])];

            foreach ($occupied[$slot['room_id']] ?? [] as $other) {
                $overlaps = $busy[0] < $other[1] && $other[0] < $busy[1];

                expect($overlaps)->toBeFalse("room {$slot['room_id']} got conflicting slots");
            }

            $occupied[$slot['room_id']][] = $busy;
        }
    }
});

test('lectures for one instructor never overlap and respect the transition buffer', function () {
    $term = engineTerm();
    lectureRoom(60);

    $instructor = freshInstructor();

    $one = oneShotSection($term, ['instructor_id' => $instructor->id]);
    $two = oneShotSection($term, ['instructor_id' => $instructor->id]);

    $result = solver()->generate($term, ['buffer_minutes' => 15]);

    expect($result['conflicts'])->toBeEmpty()
        ->and(count($result['assignments']))->toBe(2);

    $intervals = [];

    foreach ([$one, $two] as $section) {
        $placement = placementFor($result, $section->fullCode());

        expect($placement)->not->toBeEmpty();

        foreach ($placement['slots'] as $slot) {
            $intervals[] = [$slot['day'], clockMinutes($slot['start']), clockMinutes($slot['end'])];
        }
    }

    foreach ($intervals as $x => $first) {
        foreach ($intervals as $y => $second) {
            if ($x >= $y) {
                continue;
            }

            $violating = $first[0] === $second[0]
                && $second[2] > $first[1] - 15
                && $first[2] > $second[1] - 15;

            expect($violating)->toBeFalse('instructor slots overlap or ignore the buffer');
        }
    }
});

test('students of one cohort are never double-booked', function () {
    $term = engineTerm();
    lectureRoom();

    $program = cohort('Bachelor of Rail Safety');
    $students = Student::factory()->count(2)->create(['program_id' => $program->id]);

    $one = oneShotSection($term, ['instructor_id' => freshInstructor()->id]);
    $two = oneShotSection($term, ['instructor_id' => freshInstructor()->id]);

    foreach ($students as $student) {
        enrollIn($student, $one);
        enrollIn($student, $two);
    }

    $result = solver()->generate($term);

    expect($result['conflicts'])->toBeEmpty()
        ->and(count($result['assignments']))->toBe(2);

    $slots = [];

    foreach ([$one, $two] as $section) {
        foreach (placementFor($result, $section->fullCode())['slots'] as $slot) {
            $slots[] = [$slot['day'], clockMinutes($slot['start']), clockMinutes($slot['end'])];
        }
    }

    foreach ($slots as $x => $first) {
        foreach ($slots as $y => $second) {
            if ($x >= $y) {
                continue;
            }

            $doubleBooked = $first[0] === $second[0]
                && $first[1] < $second[2]
                && $second[1] < $first[2];

            expect($doubleBooked)->toBeFalse('cohort slots overlap');
        }
    }
});

test('lab sections only land in lab rooms, otherwise a conflict is reported', function () {
    $term = engineTerm();

    $course = Course::factory()->create(['credits' => 1]);
    $section = CourseSection::factory()->create([
        'course_id' => $course->id,
        'term_id' => $term->id,
        'capacity' => 20,
        'requires_lab' => true,
    ]);

    lectureRoom(100); // big enough on capacity, wrong room type

    $result = solver()->generate($term);

    $conflict = conflictFor($result, $section->fullCode());

    expect($result['assignments'])->toBeEmpty()
        ->and($conflict)->not->toBeNull()
        ->and($conflict['reason'])->toContain('lab');

    // With a suitable lab room the same setup succeeds.
    $freshTerm = engineTerm();
    $freshSection = CourseSection::factory()->create([
        'course_id' => $course->id,
        'term_id' => $freshTerm->id,
        'capacity' => 20,
        'requires_lab' => true,
    ]);

    $lab = labRoom(24);

    $success = solver()->generate($freshTerm);

    $placement = placementFor($success, $freshSection->fullCode());

    expect($success['conflicts'])->toBeEmpty()
        ->and($placement)->not->toBeEmpty()
        ->and($placement['slots'][0]['room_id'])->toBe($lab->id);
});

test('room capacity is respected, and unreachable capacity is reported', function () {
    $term = engineTerm();
    $section = oneShotSection($term, ['capacity' => 80]);

    lectureRoom(60);

    $contradiction = solver()->generate($term);

    expect($contradiction['assignments'])->toBeEmpty()
        ->and(conflictFor($contradiction, $section->fullCode())['reason'])->toContain('capacity');

    $fresh = engineTerm();
    $placeable = oneShotSection($fresh, ['capacity' => 80]);

    lectureRoom(100);

    $result = solver()->generate($fresh);

    expect($result['conflicts'])->toBeEmpty()
        ->and(placementFor($result, $placeable->fullCode()))
        ->not->toBeEmpty();
});

test('the daily hour ceiling is enforced for cohorts', function () {
    $term = engineTerm();
    lectureRoom();

    $program = cohort('Bachelor of Two-A-Day');
    $student = Student::factory()->create(['program_id' => $program->id]);

    $one = oneShotSection($term);
    $two = oneShotSection($term);

    enrollIn($student, $one);
    enrollIn($student, $two);

    $result = solver()->generate($term, ['max_daily_hours' => 1]);

    expect($result['conflicts'])->toBeEmpty()
        ->and(count($result['assignments']))->toBe(2);

    $days = collect([$one, $two])
        ->map(fn (CourseSection $section) => placementFor($result, $section->fullCode())['slots'])
        ->flatten(1)
        ->pluck('day')
        ->unique()
        ->sort()
        ->values();

    // Two 60-minute meetings cannot share one day under a 60-minute ceiling.
    expect($days->count())->toBe(2);
});

test('sections that already carry schedules stay fixed', function () {
    $term = engineTerm();

    $fixed = oneShotSection($term);
    $loose = oneShotSection($term);

    $room = lectureRoom(30);
    SectionSchedule::factory()->at(1, '09:00', '10:30', $room)->create(['section_id' => $fixed->id]);

    $result = solver()->generate($term, ['buffer_minutes' => 0]);

    $fixedPlacement = placementFor($result, $fixed->fullCode());
    $loosePlacement = placementFor($result, $loose->fullCode());

    expect($fixedPlacement['fixed'])->toBeTrue()
        ->and($fixedPlacement['slots'][0]['day'])->toBe(1)
        ->and($fixedPlacement['slots'][0]['start'])->toBe('09:00')
        ->and($loosePlacement['fixed'])->toBeFalse();

    // The placed section must not overlap the fixed one anywhere.
    foreach ($loosePlacement['slots'] as $slot) {
        expect($slot['day'] !== 1 || clockMinutes($slot['start']) >= 630 || clockMinutes($slot['end']) <= 540)
            ->toBeTrue();
    }
});

test('regenerate_existing dissolves fixed sections into the candidate pool', function () {
    $term = engineTerm();

    $scheduled = oneShotSection($term);

    $room = lectureRoom(30);
    SectionSchedule::factory()->at(3, '17:00', '18:00', $room)->create(['section_id' => $scheduled->id]);

    $result = solver()->generate($term, ['regenerate_existing' => true]);

    $placement = placementFor($result, $scheduled->fullCode());

    expect($placement['fixed'])->toBeFalse()
        ->and($placement['slots'][0]['start'])->toBe('08:00');
});

test('the draft is deterministic across repeated runs', function () {
    $term = engineTerm();
    $program = cohort('Bachelor of Determinism');
    $students = Student::factory()->count(3)->create(['program_id' => $program->id]);

    foreach (range(1, 6) as $i) { // 6 sections of varying sizes, shared cohort
        $section = oneShotSection($term, ['capacity' => 30]);
        Room::factory()->create(['code' => nextRoomCode(), 'capacity' => 40]);

        enrollIn($students[$i % 3], $section);
    }

    $first = solver()->generate($term);
    $second = solver()->generate($term);

    expect($first)->toBe($second);
});

test('manual adjustments are validated against the committed draft', function () {
    $term = engineTerm();

    $fixed = oneShotSection($term);
    $loose = oneShotSection($term);

    $busyRoom = lectureRoom(30);
    $freeRoom = lectureRoom(30);

    SectionSchedule::factory()->at(1, '09:00', '10:30', $busyRoom)->create(['section_id' => $fixed->id]);

    $settings = ['buffer_minutes' => 0];
    $draft = solver()->generate($term, $settings);

    expect($draft['conflicts'])->toBeEmpty()
        ->and(count($draft['assignments']))->toBe(2);

    // Landing on the fixed section's room at the same time must be rejected.
    $rejected = solver()->validateAdjustment($term, $loose->id, [
        ['day' => 1, 'start' => '09:30', 'end' => '10:30', 'room_id' => $busyRoom->id],
    ], $draft['assignments'], $settings);

    expect($rejected)->not->toBeEmpty();

    // A clean slot in a free room stays valid.
    $accepted = solver()->validateAdjustment($term, $loose->id, [
        ['day' => 2, 'start' => '11:00', 'end' => '12:00', 'room_id' => $freeRoom->id],
    ], $draft['assignments'], $settings);

    expect($accepted)->toBeEmpty();

    // Moving into the fixed room *after* it frees up (no buffer) is valid.
    $followOn = solver()->validateAdjustment($term, $loose->id, [
        ['day' => 1, 'start' => '10:30', 'end' => '11:30', 'room_id' => $busyRoom->id],
    ], $draft['assignments'], $settings);

    expect($followOn)->toBeEmpty();
});
