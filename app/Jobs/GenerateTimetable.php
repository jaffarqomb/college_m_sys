<?php

namespace App\Jobs;

use App\Enums\TimetableGenerationStatus;
use App\Models\TimetableGeneration;
use App\Services\Timetable\TimetableGeneratorService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

/**
 * Run the timetable solver for a queued draft generation.
 *
 * The caller (TimetableBuilder page) creates the generation record in the
 * "generating" state; this job fills it in (or flags a failure) once the
 * solver finishes. Section schedules are only written on publish.
 */
class GenerateTimetable implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new job instance.
     */
    public function __construct(public TimetableGeneration $generation)
    {
        $this->onQueue('timetable');
    }

    /**
     * Execute the job.
     */
    public function handle(TimetableGeneratorService $generator): void
    {
        $generation = $this->generation->refresh();

        if ($generation->status !== TimetableGenerationStatus::Generating) {
            return;
        }

        try {
            $result = $generator->generate($generation->term, $generation->settings);

            $generation->update([
                'status' => TimetableGenerationStatus::Draft,
                'assignments' => $result['assignments'],
                'conflicts' => $result['conflicts'],
                'fitness' => $result['fitness'],
            ]);
        } catch (Throwable $exception) {
            report($exception);

            $generation->update(['status' => TimetableGenerationStatus::Failed]);
        }
    }
}
