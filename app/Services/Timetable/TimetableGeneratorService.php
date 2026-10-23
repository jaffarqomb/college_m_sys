<?php

namespace App\Services\Timetable;

use App\Models\AcademicTerm;
use App\Models\CourseSection;
use App\Models\Room;
use App\Models\SectionSchedule;
use Illuminate\Support\Collection;

/**
 * Deterministic timetable constraint solver.
 *
 * Sections are placed greedily — hardest first (lab requirement, capacity,
 * meeting count) — over a deterministic enumeration of candidate patterns
 * (day combinations × start times), with a bounded one-level backtracking
 * repair pass. The whole draft is scored with a weighted fitness.
 *
 * Hard constraints: room/instructor/cohort double-booking, lab room matching,
 * room capacity, day/time window, instructor transition buffers and daily
 * hour ceilings for instructors and student cohorts.
 *
 * Nothing is written to SectionSchedule here — the returned draft is
 * normalized data the builder page can display, adjust and publish.
 *
 * @phpstan-type Slot array{day: int, start: int, end: int, room_id: int|null}
 * @phpstan-type DraftSlot array{day: int, start: string, end: string, room_id: int|null}
 * @phpstan-type BusyWindow array{0: int, 1: int}
 * @phpstan-type BusyMap array<int, array<int, list<BusyWindow>>>
 * @phpstan-type Committed array{rooms: BusyMap, instructors: BusyMap, cohorts: BusyMap}
 * @phpstan-type Spec array{section_id: int, code: string, course: string, instructor_id: int|null, instructor: string|null, cohorts: list<int>, capacity: int, requires_lab: bool, meetings: int, length: int, fixed: bool, slots: list<Slot>}
 * @phpstan-type PlacementEntry array{spec: Spec, slots: list<Slot>}
 * @phpstan-type Placement array{section_id: int, code: string, course: string, instructor: string|null, fixed: bool, slots: list<DraftSlot>}
 * @phpstan-type Conflict array{section_id: int, section: string, course: string, reason: string}
 * @phpstan-type Settings array{days: list<int>, day_start: string, day_end: string, granularity: int, max_daily_hours: int, buffer_minutes: int, lab_room_type: string, regenerate_existing: bool}
 * @phpstan-type Result array{assignments: list<Placement>, conflicts: list<Conflict>, fitness: float}
 */
class TimetableGeneratorService
{
    /**
     * Get the default configuration used by the builder UI.
     *
     * @return Settings
     */
    public static function defaultSettings(): array
    {
        return [
            'days' => [1, 2, 3, 4, 5],
            'day_start' => '08:00',
            'day_end' => '18:00',
            'granularity' => 30,
            'max_daily_hours' => 5,
            'buffer_minutes' => 15,
            'lab_room_type' => 'lab',
            'regenerate_existing' => false,
        ];
    }

    /**
     * Generate a full draft timetable for the term.
     *
     * Sections that already carry schedules act as immovable constraints
     * unless the "regenerate_existing" setting is enabled; everything else
     * is placed. No SectionSchedule rows are touched — publishing happens
     * through the builder page.
     *
     * @param  array<string, mixed>  $settings
     * @return Result
     */
    public function generate(AcademicTerm $term, array $settings = []): array
    {
        $settings = $this->mergeSettings($settings);

        $rooms = Room::query()->orderBy('capacity')->orderBy('id')->get()->values();

        $all = $this->plannableSections($term->id)
            ->map(fn (CourseSection $section) => $this->specOf($section, $settings))
            ->values()
            ->all();

        $fixedSpecs = $settings['regenerate_existing']
            ? []
            : array_values(array_filter($all, fn (array $spec): bool => (bool) $spec['fixed']));

        /** @var list<int> $fixedIds */
        $fixedIds = array_map(fn (array $spec): int => $spec['section_id'], $fixedSpecs);

        $candidates = collect($all)
            ->reject(fn (array $spec): bool => in_array($spec['section_id'], $fixedIds, true))
            ->sortByDesc(fn (array $spec): int => ($spec['requires_lab'] ? 1_000_000 : 0) + $spec['capacity'] * 100 + $spec['meetings'])
            ->values()
            ->all();

        $committed = $this->emptyCommitted();

        foreach ($fixedSpecs as $spec) {
            $this->commit($spec, $spec['slots'], $committed);
        }

        /** @var array<int, PlacementEntry> $placements */
        $placements = [];

        /** @var list<int> $placedOrder placement order, newest last */
        $placedOrder = [];

        /** @var list<Conflict> $conflicts */
        $conflicts = [];

        foreach ($candidates as $spec) {
            $slots = $this->bestPlacement($spec, $committed, $rooms, $settings);

            if ($slots !== null) {
                $this->commit($spec, $slots, $committed);
                $placements[$spec['section_id']] = ['spec' => $spec, 'slots' => $slots];
                $placedOrder[] = $spec['section_id'];

                continue;
            }

            if (! $this->attemptRepair($spec, $placements, $placedOrder, $committed, $rooms, $settings)) {
                $conflicts[] = [
                    'section_id' => $spec['section_id'],
                    'section' => $spec['code'],
                    'course' => $spec['course'],
                    'reason' => $this->conflictReason($spec, $rooms, $settings),
                ];
            }
        }

        $allPlacements = [];

        foreach ($fixedSpecs as $spec) {
            $allPlacements[$spec['section_id']] = ['spec' => $spec, 'slots' => $spec['slots']];
        }

        $allPlacements += $placements;

        return [
            'assignments' => $this->formatAssignments($allPlacements),
            'conflicts' => $conflicts,
            'fitness' => $this->fitness($rooms, $allPlacements, $settings),
        ];
    }

    /**
     * Validate a manually adjusted slot list for one section against the
     * rest of its draft. Returns human-readable violations (empty = valid).
     *
     * @param  list<DraftSlot>  $newSlots  clock-string slots the operator chose
     * @param  list<Placement>  $assignments  draft placements
     * @param  array<string, mixed>  $settings
     * @return list<string>
     */
    public function validateAdjustment(AcademicTerm $term, int $sectionId, array $newSlots, array $assignments, array $settings = []): array
    {
        $settings = $this->mergeSettings($settings);
        $newSlots = $this->toMinuteSlots($newSlots);
        $rooms = Room::query()->get()->keyBy('id');

        $specs = $this->plannableSections($term->id)
            ->mapWithKeys(fn (CourseSection $section): array => [$section->id => $this->specOf($section, $settings)])
            ->all();

        $movedSpec = $specs[$sectionId] ?? null;

        if ($movedSpec === null) {
            return ["Section #{$sectionId} is not offered in this term."];
        }

        $committed = $this->emptyCommitted();

        foreach ($assignments as $assignment) {
            if ((int) $assignment['section_id'] === $sectionId) {
                continue;
            }

            $other = $specs[$assignment['section_id']] ?? null;

            if ($other !== null) {
                $this->commit($other, $this->toMinuteSlots($assignment['slots']), $committed);
            }
        }

        /** @var list<string> $violations */
        $violations = [];

        foreach ($newSlots as $slot) {
            if (! $this->withinDayWindow($slot, $settings)) {
                $violations[] = "{$movedSpec['code']}: the slot falls outside the allowed day/time window.";
            }

            $room = $slot['room_id'] !== null ? ($rooms[$slot['room_id']] ?? null) : null;

            if ($room === null) {
                $violations[] = "{$movedSpec['code']}: no room is assigned to the slot.";

                continue;
            }

            if (! $this->roomFits($room, $movedSpec, $settings)) {
                $violations[] = "{$movedSpec['code']}: {$room->code} is unsuitable — it is either not a lab room or too small.";
            }

            if (! $this->roomFree($room, $slot['day'], $slot['start'], $slot['end'], $committed)) {
                $violations[] = "{$movedSpec['code']}: {$room->code} is already booked at that time.";
            }

            if (! $this->instructorFree($movedSpec, $slot['day'], $slot['start'], $slot['end'], $committed, $settings)) {
                $violations[] = "{$movedSpec['code']}: the instructor would be double-booked (or lacks a transition buffer).";
            }

            if (! $this->cohortFree($movedSpec, $slot['day'], $slot['start'], $slot['end'], $committed)) {
                $violations[] = "{$movedSpec['code']}: enrolled student groups would be double-booked.";
            }
        }

        if ($violations === [] && ! $this->dailyHoursOk($movedSpec, $newSlots, $committed, $settings)) {
            $violations[] = "{$movedSpec['code']}: an instructor or student group would exceed the {$settings['max_daily_hours']}h daily limit.";
        }

        return array_values(array_unique($violations));
    }

    /**
     * Merge partial settings with defaults, clamping values into range.
     *
     * @param  array<string, mixed>  $settings
     * @return Settings
     */
    public function mergeSettings(array $settings): array
    {
        $defaults = self::defaultSettings();

        $rawDays = $settings['days'] ?? $defaults['days'];
        $days = is_array($rawDays) ? $rawDays : [];

        return [
            'days' => array_values(collect($days)
                ->map(fn ($day): int => (int) $day)
                ->unique()
                ->sort()
                ->values()
                ->all()),
            'day_start' => (string) ($settings['day_start'] ?? $defaults['day_start']),
            'day_end' => (string) ($settings['day_end'] ?? $defaults['day_end']),
            'granularity' => max(15, (int) ($settings['granularity'] ?? 30)),
            'max_daily_hours' => max(1, (int) ($settings['max_daily_hours'] ?? 5)),
            'buffer_minutes' => max(0, (int) ($settings['buffer_minutes'] ?? 15)),
            'lab_room_type' => (string) ($settings['lab_room_type'] ?? $defaults['lab_room_type']),
            'regenerate_existing' => (bool) ($settings['regenerate_existing'] ?? false),
        ];
    }

    /**
     * Derive the fitting specification for a section given merged settings.
     *
     * Meetings: lab sections meet once weekly; lecture sections follow the
     * course credits (1 credit = 1 h/week) split across at most two meetings.
     * Slot lengths round up to the configured granularity.
     *
     * @param  Settings  $settings
     * @return Spec
     */
    public function specOf(CourseSection $section, array $settings): array
    {
        $course = $section->course;
        $granularity = $settings['granularity'];

        if ($section->requires_lab) {
            $meetings = 1;
            $length = max($granularity, (int) ceil(120 / $granularity) * $granularity);
        } else {
            $meetings = min(2, max(1, (int) ceil($course->credits / 2)));
            $length = max($granularity, (int) ceil($course->credits * 60 / $meetings / $granularity) * $granularity);
        }

        /** @var list<Slot> $slots */
        $slots = $section->sectionSchedules
            ->map(fn (SectionSchedule $schedule) => [
                'day' => (int) $schedule->day_of_week,
                'start' => $this->toMinutes((string) $schedule->start_time),
                'end' => $this->toMinutes((string) $schedule->end_time),
                'room_id' => $schedule->room_id,
            ])
            ->values()
            ->all();

        /** @var list<int> $cohorts */
        $cohorts = $section->enrollments
            ->map(fn ($enrollment): ?int => $enrollment->student->program_id)
            ->filter(fn ($programId): bool => $programId !== null)
            ->map(fn ($programId): int => (int) $programId)
            ->unique()
            ->values()
            ->all();

        return [
            'section_id' => $section->id,
            'code' => $section->fullCode(),
            'course' => $course->title,
            'instructor_id' => $section->instructor_id,
            'instructor' => $section->instructor?->displayName(),
            'cohorts' => $cohorts,
            'capacity' => $section->capacity,
            'requires_lab' => $section->requires_lab,
            'meetings' => $meetings,
            'length' => $length,
            'fixed' => ! $settings['regenerate_existing'] && $section->sectionSchedules->isNotEmpty(),
            'slots' => $slots,
        ];
    }

    /**
     * All sections of the term together with the data the spec needs.
     *
     * @return Collection<int, CourseSection>
     */
    protected function plannableSections(int $termId): Collection
    {
        return CourseSection::query()
            ->where('term_id', $termId)
            ->orderBy('id')
            ->with(['course', 'instructor.user', 'enrollments.student', 'sectionSchedules'])
            ->get();
    }

    /**
     * @return Committed
     */
    protected function emptyCommitted(): array
    {
        return ['rooms' => [], 'instructors' => [], 'cohorts' => []];
    }

    /**
     * The deterministic bounded-backtracking repair pass.
     *
     * @param  Spec  $spec  the section that could not be placed
     * @param  array<int, PlacementEntry>  $placements
     * @param  list<int>  $placedOrder
     * @param  Committed  $committed
     * @param  Collection<int, Room>  $rooms
     * @param  Settings  $settings
     */
    protected function attemptRepair(array $spec, array &$placements, array &$placedOrder, array &$committed, Collection $rooms, array $settings): bool
    {
        /** @var list<int> $victims */
        $victims = array_reverse($placedOrder);

        foreach ($victims as $victimId) {
            $victim = $placements[$victimId];

            $this->uncommit($victim['spec'], $victim['slots'], $committed);
            unset($placements[$victimId]);
            $placedOrder = array_values(array_filter($placedOrder, fn (int $id): bool => $id !== $victimId));

            $ours = $this->bestPlacement($spec, $committed, $rooms, $settings);

            if ($ours === null) {
                $this->commit($victim['spec'], $victim['slots'], $committed);
                $placements[$victimId] = $victim;
                $placedOrder[] = $victimId;

                continue;
            }

            // Tentatively keep the new placement committed while testing
            // whether the displaced section can find a home elsewhere.
            $this->commit($spec, $ours, $committed);

            $theirSlots = $this->bestPlacement($victim['spec'], $committed, $rooms, $settings);

            if ($theirSlots === null) {
                // Our placement is only possible at the victim's expense —
                // roll back so the victim keeps its schedule.
                $this->uncommit($spec, $ours, $committed);
                $this->commit($victim['spec'], $victim['slots'], $committed);
                $placements[$victimId] = $victim;
                $placedOrder[] = $victimId;

                continue;
            }

            $placements[$spec['section_id']] = ['spec' => $spec, 'slots' => $ours];
            $placedOrder[] = $spec['section_id'];

            $this->commit($victim['spec'], $theirSlots, $committed);
            $placements[$victimId] = ['spec' => $victim['spec'], 'slots' => $theirSlots];
            $placedOrder[] = $victimId;

            return true;
        }

        return false;
    }

    /**
     * Best (deterministic first) feasible placement for a section.
     *
     * @param  Spec  $spec
     * @param  Committed  $committed
     * @param  Collection<int, Room>  $rooms
     * @param  Settings  $settings
     * @return list<Slot>|null
     */
    protected function bestPlacement(array $spec, array $committed, Collection $rooms, array $settings): ?array
    {
        $dayStart = $this->toMinutes($settings['day_start']);
        $dayEnd = $this->toMinutes($settings['day_end']);

        $roomsPool = $rooms->filter(fn (Room $room): bool => $this->roomFits($room, $spec, $settings))->values();

        if ($roomsPool->isEmpty()) {
            return null;
        }

        foreach ($this->patterns($spec, $settings, $dayStart, $dayEnd) as $pattern) {
            $slots = $this->materialize($spec, $pattern, $roomsPool, $committed, $settings);

            if ($slots !== null && $this->dailyHoursOk($spec, $slots, $committed, $settings)) {
                return $slots;
            }
        }

        return null;
    }

    /**
     * Enumerate slot patterns: distinct day combinations × earliest-first
     * start times, keeping all meetings at the same time of day.
     *
     * @param  Spec  $spec
     * @param  Settings  $settings
     * @return list<list<array{day: int, start: int, end: int}>>
     */
    protected function patterns(array $spec, array $settings, int $dayStart, int $dayEnd): array
    {
        /** @var list<list<array{day: int, start: int, end: int}>> $patterns */
        $patterns = [];

        foreach ($this->dayCombinations($settings['days'], $spec['meetings']) as $days) {
            for ($start = $dayStart; $start + $spec['length'] <= $dayEnd; $start += $settings['granularity']) {
                $patterns[] = array_map(fn (int $day): array => [
                    'day' => $day,
                    'start' => $start,
                    'end' => $start + $spec['length'],
                ], $days);
            }
        }

        return $patterns;
    }

    /**
     * All ascending day combinations of the wanted length.
     *
     * @param  list<int>  $days
     * @return list<list<int>>
     */
    protected function dayCombinations(array $days, int $count): array
    {
        if ($count <= 0) {
            return [];
        }

        if ($count === 1) {
            return array_map(fn (int $day): array => [$day], $days);
        }

        /** @var list<list<int>> $combos */
        $combos = [];

        foreach ($days as $index => $day) {
            foreach ($this->dayCombinations(array_slice($days, $index + 1), $count - 1) as $rest) {
                $combos[] = [$day, ...$rest];
            }
        }

        return $combos;
    }

    /**
     * Attach a free room to each pattern slot, honoring all hard rules.
     *
     * @param  Spec  $spec
     * @param  list<array{day: int, start: int, end: int}>  $pattern
     * @param  Collection<int, Room>  $roomsPool
     * @param  Committed  $committed
     * @param  Settings  $settings
     * @return list<Slot>|null
     */
    protected function materialize(array $spec, array $pattern, Collection $roomsPool, array $committed, array $settings): ?array
    {
        $slots = [];

        foreach ($pattern as $slot) {
            // Prefer the least-loaded room, breaking ties by tightest capacity
            // then room id — small rooms stay available for small cohorts.
            $room = $roomsPool
                ->filter(fn (Room $candidate): bool => $this->roomFree($candidate, $slot['day'], $slot['start'], $slot['end'], $committed))
                ->sortBy(fn (Room $candidate): array => [$this->roomLoad($candidate, $slot['day'], $committed), $candidate->capacity, $candidate->id])
                ->first();

            if ($room === null) {
                return null;
            }

            if (! $this->instructorFree($spec, $slot['day'], $slot['start'], $slot['end'], $committed, $settings)
                || ! $this->cohortFree($spec, $slot['day'], $slot['start'], $slot['end'], $committed)) {
                return null;
            }

            $slots[] = ['day' => $slot['day'], 'start' => $slot['start'], 'end' => $slot['end'], 'room_id' => $room->id];
        }

        return $slots;
    }

    /**
     * Whether the room physically fits the section (type + capacity).
     *
     * @param  Spec  $spec
     * @param  Settings  $settings
     */
    protected function roomFits(Room $room, array $spec, array $settings): bool
    {
        if ($spec['requires_lab'] && $room->type !== $settings['lab_room_type']) {
            return false;
        }

        return $room->capacity >= $spec['capacity'];
    }

    /**
     * Whether the room is free for the slot on that day.
     *
     * @param  Committed  $committed
     */
    protected function roomFree(Room $room, int $day, int $start, int $end, array $committed): bool
    {
        foreach ($committed['rooms'][$room->id][$day] ?? [] as $busy) {
            if ($start < $busy[1] && $busy[0] < $end) {
                return false;
            }
        }

        return true;
    }

    /**
     * Whether the section's instructor (with transition buffer) is free.
     *
     * @param  Spec  $spec
     * @param  Committed  $committed
     * @param  Settings  $settings
     */
    protected function instructorFree(array $spec, int $day, int $start, int $end, array $committed, array $settings): bool
    {
        if ($spec['instructor_id'] === null) {
            return true;
        }

        $buffer = $settings['buffer_minutes'];

        foreach ($committed['instructors'][$spec['instructor_id']][$day] ?? [] as $busy) {
            if ($start - $buffer < $busy[1] && $end > $busy[0] - $buffer) {
                return false;
            }
        }

        return true;
    }

    /**
     * Whether no involved student cohort is double-booked.
     *
     * @param  Spec  $spec
     * @param  Committed  $committed
     */
    protected function cohortFree(array $spec, int $day, int $start, int $end, array $committed): bool
    {
        foreach ($spec['cohorts'] as $programId) {
            foreach ($committed['cohorts'][$programId][$day] ?? [] as $busy) {
                if ($start < $busy[1] && $busy[0] < $end) {
                    return false;
                }
            }
        }

        return true;
    }

    /**
     * Daily hour ceiling for both the instructor and every involved cohort.
     *
     * @param  Spec  $spec
     * @param  list<Slot>  $slots
     * @param  Committed  $committed
     * @param  Settings  $settings
     */
    protected function dailyHoursOk(array $spec, array $slots, array $committed, array $settings): bool
    {
        $cap = $settings['max_daily_hours'] * 60;

        $addsForDay = fn (int $day): int => array_reduce(
            $slots,
            fn (int $carry, array $slot): int => $slot['day'] === $day ? $carry + ($slot['end'] - $slot['start']) : $carry,
            0
        );

        foreach ($slots as $slot) {
            if ($spec['instructor_id'] !== null) {
                $booked = $this->bookedMinutes($committed['instructors'][$spec['instructor_id']][$slot['day']] ?? []);

                if ($booked + $addsForDay($slot['day']) > $cap) {
                    return false;
                }
            }

            foreach ($spec['cohorts'] as $programId) {
                $booked = $this->bookedMinutes($committed['cohorts'][$programId][$slot['day']] ?? []);

                if ($booked + $addsForDay($slot['day']) > $cap) {
                    return false;
                }
            }
        }

        return true;
    }

    /**
     * Sum occupied minutes from a busy list.
     *
     * @param  list<BusyWindow>  $busy
     */
    protected function bookedMinutes(array $busy): int
    {
        $total = 0;

        foreach ($busy as $range) {
            $total += $range[1] - $range[0];
        }

        return $total;
    }

    /**
     * Total minutes the room is already booked within a day.
     *
     * @param  Committed  $committed
     */
    protected function roomLoad(Room $room, int $day, array $committed): int
    {
        return $this->bookedMinutes($committed['rooms'][$room->id][$day] ?? []);
    }

    /**
     * Whether a slot falls inside the configured day window.
     *
     * @param  Slot  $slot
     * @param  Settings  $settings
     */
    protected function withinDayWindow(array $slot, array $settings): bool
    {
        return $slot['start'] >= $this->toMinutes($settings['day_start'])
            && $slot['end'] <= $this->toMinutes($settings['day_end'])
            && in_array($slot['day'], $settings['days'], true);
    }

    /**
     * Commit a spec's slots into the shared busy maps.
     *
     * @param  Spec  $spec
     * @param  list<Slot>  $slots
     * @param  Committed  $committed
     */
    protected function commit(array $spec, array $slots, array &$committed): void
    {
        foreach ($slots as $slot) {
            if ($slot['room_id'] === null) {
                continue;
            }

            $committed['rooms'][$slot['room_id']][$slot['day']][] = [$slot['start'], $slot['end']];

            if ($spec['instructor_id'] !== null) {
                $committed['instructors'][$spec['instructor_id']][$slot['day']][] = [$slot['start'], $slot['end']];
            }

            foreach ($spec['cohorts'] as $programId) {
                $committed['cohorts'][$programId][$slot['day']][] = [$slot['start'], $slot['end']];
            }
        }
    }

    /**
     * Remove a spec's committed slots during backtracking.
     *
     * @param  Spec  $spec
     * @param  list<Slot>  $slots
     * @param  Committed  $committed
     */
    protected function uncommit(array $spec, array $slots, array &$committed): void
    {
        foreach ($slots as $slot) {
            if ($slot['room_id'] !== null) {
                $committed['rooms'][$slot['room_id']][$slot['day']] = array_values(
                    array_filter(
                        $committed['rooms'][$slot['room_id']][$slot['day']] ?? [],
                        fn (array $busy): bool => $busy !== [$slot['start'], $slot['end']]
                    )
                );
            }

            if ($spec['instructor_id'] !== null) {
                $committed['instructors'][$spec['instructor_id']][$slot['day']] = array_values(
                    array_filter(
                        $committed['instructors'][$spec['instructor_id']][$slot['day']] ?? [],
                        fn (array $busy): bool => $busy !== [$slot['start'], $slot['end']]
                    )
                );
            }

            foreach ($spec['cohorts'] as $programId) {
                $committed['cohorts'][$programId][$slot['day']] = array_values(
                    array_filter(
                        $committed['cohorts'][$programId][$slot['day']] ?? [],
                        fn (array $busy): bool => $busy !== [$slot['start'], $slot['end']]
                    )
                );
            }
        }
    }

    /**
     * Human-readable reason why a section could not be placed.
     *
     * @param  Spec  $spec
     * @param  Collection<int, Room>  $rooms
     * @param  Settings  $settings
     */
    protected function conflictReason(array $spec, Collection $rooms, array $settings): string
    {
        $fits = $rooms->filter(fn (Room $room): bool => $this->roomFits($room, $spec, $settings))->isNotEmpty();

        if (! $fits) {
            $type = $spec['requires_lab'] ? "lab ({$settings['lab_room_type']})" : 'lecture';

            return sprintf('No %s room with capacity >= %d exists.', $type, $spec['capacity']);
        }

        return 'Every viable room, instructor and cohort slot combination conflicts with the committed timetable.';
    }

    /**
     * Weighted fitness: room utilization (60%) + instructor compactness (40%).
     *
     * @param  Collection<int, Room>  $rooms
     * @param  array<int, PlacementEntry>  $placements
     * @param  Settings  $settings
     */
    protected function fitness(Collection $rooms, array $placements, array $settings): float
    {
        $window = ($this->toMinutes($settings['day_end']) - $this->toMinutes($settings['day_start'])) * count($settings['days']);
        $capacityMinutes = count($rooms) * $window;

        if ($capacityMinutes === 0) {
            return 0.0;
        }

        $booked = 0;

        foreach ($placements as $entry) {
            foreach ($entry['slots'] as $slot) {
                $booked += $slot['end'] - $slot['start'];
            }
        }

        $utilization = min(1.0, $booked / $capacityMinutes);

        $idle = 0;
        $span = 0;

        foreach ($placements as $entry) {
            if ($entry['spec']['instructor_id'] === null) {
                continue;
            }

            $byDay = [];

            foreach ($entry['slots'] as $slot) {
                $byDay[$slot['day']][] = $slot;
            }

            foreach ($byDay as $sameDay) {
                $first = min(array_column($sameDay, 'start'));
                $last = max(array_column($sameDay, 'end'));
                $busy = array_sum(array_map(fn (array $slot): int => $slot['end'] - $slot['start'], $sameDay));

                $span += $last - $first;
                $idle += ($last - $first) - $busy;
            }
        }

        $compactness = $span > 0 ? 1 - ($idle / $span) : 1.0;

        return round(min(1.0, max(0.0, 0.6 * $utilization + 0.4 * $compactness)), 3);
    }

    /**
     * Turn placements (minute slots + specs) into the persisted draft shape
     * with clock-string times, sorted by section id for stable display.
     *
     * @param  array<int, PlacementEntry>  $placements
     * @return list<Placement>
     */
    protected function formatAssignments(array $placements): array
    {
        usort($placements, fn (array $a, array $b): int => $a['spec']['section_id'] <=> $b['spec']['section_id']);

        return array_map(fn (array $entry) => [
            'section_id' => $entry['spec']['section_id'],
            'code' => $entry['spec']['code'],
            'course' => $entry['spec']['course'],
            'instructor' => $entry['spec']['instructor'],
            'fixed' => $entry['spec']['fixed'],
            'slots' => array_map(
                fn (array $slot): array => [
                    'day' => $slot['day'],
                    'start' => $this->toClock((int) $slot['start']),
                    'end' => $this->toClock((int) $slot['end']),
                    'room_id' => $slot['room_id'] !== null ? (int) $slot['room_id'] : null,
                ],
                $entry['slots']
            ),
        ], $placements);
    }

    /**
     * Convert draft clock-string placements back into minute slots.
     *
     * @param  list<array<string, mixed>>  $slots
     * @return list<Slot>
     */
    public function toMinuteSlots(array $slots): array
    {
        return array_map(
            fn (array $slot): array => [
                'day' => (int) ($slot['day'] ?? 0),
                'start' => $this->toMinutes((string) ($slot['start'] ?? '00:00')),
                'end' => $this->toMinutes((string) ($slot['end'] ?? '00:00')),
                'room_id' => ($slot['room_id'] !== null && $slot['room_id'] !== '') ? (int) $slot['room_id'] : null,
            ],
            $slots
        );
    }

    /**
     * Convert "HH:MM:SS" (or "HH:MM") into minutes since midnight.
     */
    public function toMinutes(string $time): int
    {
        $parts = sscanf($time, '%d:%d');

        return ($parts[0] ?? 0) * 60 + ($parts[1] ?? 0);
    }

    /**
     * Convert minutes since midnight back into "HH:MM".
     */
    public function toClock(int $minutes): string
    {
        return sprintf('%02d:%02d', intdiv($minutes, 60), $minutes % 60);
    }
}
