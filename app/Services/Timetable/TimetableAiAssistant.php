<?php

declare(strict_types=1);

namespace App\Services\Timetable;

use App\Models\AcademicTerm;
use App\Models\CourseSection;
use App\Models\Room;
use App\Models\SectionSchedule;
use App\Services\OllamaService;
use Illuminate\Support\Collection;
use RuntimeException;

/**
 * Local-LLM advisory layer over the deterministic timetable solver.
 *
 * The solver stays the source of truth for placement and validation; Ollama
 * (via OllamaService, at services.ollama.url) only reviews drafts and proposes
 * human-actionable conflict fixes — every proposal must still pass
 * TimetableGeneratorService::validateAdjustment before it reaches a schedule.
 *
 * @phpstan-import-type Placement from TimetableGeneratorService
 * @phpstan-import-type Conflict from TimetableGeneratorService
 *
 * @phpstan-type ResultPresentation array{assignments: list<Placement>, conflicts: list<Conflict>, fitness: float|null}
 */
class TimetableAiAssistant
{
    public function __construct(private readonly OllamaService $ollama) {}

    /**
     * Critique a generated draft and propose concrete improvement moves.
     *
     * @param  ResultPresentation  $result
     * @param  array<string, mixed>  $settings
     */
    public function reviewDraft(AcademicTerm $term, array $result, array $settings): string
    {
        $this->assertAvailable();

        $rooms = Room::query()->get()->keyBy('id');

        $assignmentLines = collect($result['assignments'])
            ->map(fn (array $assignment): string => sprintf(
                '- %s: %s',
                (string) $assignment['code'],
                collect($assignment['slots'])
                    ->map(fn (array $slot): string => sprintf(
                        '%s %s-%s room %s',
                        $this->dayName((int) $slot['day']),
                        (string) $slot['start'],
                        (string) $slot['end'],
                        $slot['room_id'] !== null ? ($rooms[$slot['room_id']]->code ?? '?') : 'none',
                    ))
                    ->implode(', '),
            ))
            ->implode("\n");

        $prompt = "You are a university scheduling advisor. Improvements must respect the hard constraints:\n"
            ."- no double-booked rooms, instructors or student cohorts, labs only in lab rooms, room capacity,\n"
            ."  the day/time window, and the maximum daily hours per instructor or cohort.\n\n"
            ."Term: {$term->name}\n"
            .$this->settingsLine($settings)."\n\n"
            ."Rooms (code, seats, type):\n"
            .$this->roomInventory()."\n\n"
            ."Draft placements (fitness score: {$this->fitnessOf($result)}):\n"
            ."$assignmentLines\n\n"
            ."Unplaced / conflicting sections:\n"
            .$this->conflictLines($result['conflicts'])."\n\n"
            ."In at most 8 bullet points, review this draft and give concrete, specific moves that would\n"
            ."improve room utilization or timetable compactness (which section moves where, or which room\n"
            ."to swap), always mentioning the section codes. Never propose anything that breaks the\n"
            ."constraints above. Plain text only.\n";

        return $this->ollama->chat($prompt);
    }

    /**
     * Ask the model for repair suggestions for the sections the solver
     * could not place, given the same constraint set.
     *
     * @param  list<Conflict>  $conflicts
     * @param  array<string, mixed>  $settings
     */
    public function suggestConflictFixes(AcademicTerm $term, array $conflicts, array $settings): string
    {
        $this->assertAvailable();

        $detailLines = [];

        $sections = CourseSection::query()
            ->whereIn('id', array_column($conflicts, 'section_id'))
            ->with(['course', 'instructor.user', 'enrollments'])
            ->get()
            ->keyBy('id');

        foreach ($conflicts as $conflict) {
            /** @var CourseSection|null $section */
            $section = $sections[$conflict['section_id']] ?? null;

            if ($section === null) {
                $detailLines[] = "- {$conflict['section']}: {$conflict['reason']}";

                continue;
            }

            $detailLines[] = sprintf(
                '- %s (%s): capacity %d%s, %d enrolled, instructor %s. Blocker: %s',
                $section->fullCode(),
                $section->course->title,
                $section->capacity,
                $section->requires_lab ? ', requires a lab room' : '',
                $section->enrollments->count(),
                $section->instructor?->user->name ?? 'unassigned',
                $conflict['reason'],
            );
        }

        $details = implode("\n", $detailLines);

        $prompt = "You are a university scheduling advisor. The deterministic solver could not place the sections\n"
            ."below. Treat these as hard constraints: no double-booked rooms, instructors or cohorts, labs\n"
            ."only in lab rooms, room capacity, the day/time window, and the maximum daily hours.\n\n"
            ."Term: {$term->name}\n"
            .$this->settingsLine($settings)."\n\n"
            ."Available rooms (code, seats, type):\n"
            .$this->roomInventory()."\n\n"
            ."Unplaced sections:\n"
            .$details."\n\n"
            ."For each section, in at most 3 short bullet points, propose specific fixes in priority order\n"
            ."(e.g. \"move to Monday 14:00-15:30 in room X\", \"free room Y by moving section Z\", \"split the\n"
            ."section\", \"assign a lab room\"). Always mention section codes. Plain text only.\n";

        return $this->ollama->chat($prompt);
    }

    /**
     * Rooms as a compact inventory list for the prompt (room ids carry no
     * meaning for the model — only code/seats/type matter).
     */
    private function roomInventory(): string
    {
        return Room::query()
            ->orderBy('code')
            ->get()
            ->map(fn (Room $room): string => sprintf('%s (%d seats, %s)', $room->code, $room->capacity, $room->type))
            ->implode("\n");
    }

    /**
     * @param  list<Conflict>  $conflicts
     */
    private function conflictLines(array $conflicts): string
    {
        if ($conflicts === []) {
            return '(none — every section was placed)';
        }

        return collect($conflicts)
            ->map(fn (array $conflict): string => "- {$conflict['section']}: {$conflict['reason']}")
            ->implode("\n");
    }

    /**
     * Compact settings summary line for both prompts.
     *
     * @param  array<string, mixed>  $settings
     */
    private function settingsLine(array $settings): string
    {
        /** @var mixed $daysSetting */
        $daysSetting = $settings['days'];

        /** @var Collection<int, int|string> $dayValues */
        $dayValues = collect(is_array($daysSetting) ? $daysSetting : []);

        $days = $dayValues
            ->map(fn (int|string $day): string => $this->dayName((int) $day))
            ->implode(', ');

        return sprintf(
            'Settings: day window %s-%s, allowed days %s, slot granularity %d minutes, max %dh per day, transition buffer %d minutes.',
            (string) $settings['day_start'],
            (string) $settings['day_end'],
            $days,
            (int) $settings['granularity'],
            (int) $settings['max_daily_hours'],
            (int) $settings['buffer_minutes'],
        );
    }

    private function dayName(int $day): string
    {
        return SectionSchedule::DAYS[$day] ?? 'day '.$day;
    }

    /**
     * @param  array<string, mixed>  $result
     */
    private function fitnessOf(array $result): string
    {
        $fitness = $result['fitness'];

        return is_numeric($fitness) ? (string) round((float) $fitness, 2) : 'unavailable';
    }

    private function assertAvailable(): void
    {
        if (! $this->ollama->isAvailable()) {
            throw new RuntimeException(sprintf(
                'The AI assistant is unavailable — Ollama is not reachable at %s.',
                (string) config('services.ollama.url'),
            ));
        }
    }
}
