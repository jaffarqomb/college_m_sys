<?php

use App\Actions\Academic\DropEnrollment;
use App\Models\AcademicTerm;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\On;
use Livewire\Component;

new class extends Component {
    public ?string $feedback = null;

    public ?string $error = null;

    /**
     * @return \Illuminate\Support\Collection<int, \App\Models\Enrollment>
     */
    public function activeEnrollments()
    {
        $student = auth()->user()?->studentProfile;

        if ($student === null) {
            return collect();
        }

        $currentTermId = AcademicTerm::query()->current()->value('id');

        return $student->enrollments()
            ->active()
            ->whereHas('section', fn ($query) => $query->where('term_id', $currentTermId))
            ->with('section.course', 'section.instructor.user', 'section.sectionSchedules')
            ->get();
    }

    public function drop(DropEnrollment $dropEnrollment, int $enrollmentId): void
    {
        $this->reset('feedback', 'error');

        $student = auth()->user()?->studentProfile;

        abort_unless($student !== null, 403);

        $enrollment = $student->enrollments()
            ->active()
            ->whereKey($enrollmentId)
            ->firstOrFail();

        try {
            $dropEnrollment->handle($enrollment);
            $this->feedback = 'You have dropped this section.';
        } catch (ValidationException $exception) {
            $this->error = $exception->errors()['drop'][0] ?? 'Unable to drop this section.';
        }

        $this->dispatch('enrollment-updated');
    }

    #[On('enrollment-updated')]
    public function refreshIsland(): void {}

    public function placeholder(): string
    {
        return <<<'HTML'
            <div class="animate-pulse space-y-3 p-6">
                <div class="h-4 w-1/2 rounded bg-zinc-200 dark:bg-zinc-700"></div>
                <div class="h-4 w-3/4 rounded bg-zinc-200 dark:bg-zinc-700"></div>
                <div class="h-4 w-2/3 rounded bg-zinc-200 dark:bg-zinc-700"></div>
            </div>
            HTML;
    }
}; ?>

<div class="rounded-2xl border border-zinc-200 bg-white shadow-sm dark:border-zinc-800 dark:bg-zinc-900" data-test="portal-my-enrollments">
    <div class="flex items-center justify-between gap-3 border-b border-zinc-200 px-6 py-4 dark:border-zinc-800">
        <div>
            <h2 class="flex items-center gap-2 text-base font-semibold text-zinc-900 dark:text-zinc-100">
                <flux:icon.academic-cap class="size-5 text-indigo-600 dark:text-indigo-400" />
                My enrollments
            </h2>
            <p class="text-sm text-zinc-500 dark:text-zinc-400">{{ \App\Models\AcademicTerm::query()->current()->value('name') ?? 'No active term' }}</p>
        </div>
    </div>

    <div class="p-6">
        @if ($error)
            <p class="mb-4 rounded-lg bg-red-50 px-3 py-2 text-sm text-red-700 dark:bg-red-950/50 dark:text-red-300" wire:key="drop-error-{{ md5($error) }}">{{ $error }}</p>
        @elseif ($feedback)
            <p class="mb-4 rounded-lg bg-emerald-50 px-3 py-2 text-sm text-emerald-700 dark:bg-emerald-950/50 dark:text-emerald-300" wire:key="drop-feedback-{{ md5($feedback) }}">{{ $feedback }}</p>
        @endif

        @if ($this->activeEnrollments()->isEmpty())
            <p class="text-sm text-zinc-500 dark:text-zinc-400">You have no active enrollments this term.</p>
        @else
            <ul class="space-y-4" wire:loading.class="animate-pulse" wire:target="drop">
                @foreach ($this->activeEnrollments() as $enrollment)
                    <li class="flex items-start justify-between gap-4 rounded-xl border border-zinc-200 p-4 dark:border-zinc-800" wire:key="enrollment-{{ $enrollment->id }}">
                        <div>
                            <p class="font-medium text-zinc-900 dark:text-zinc-100">
                                {{ $enrollment->section->course->code }} — {{ $enrollment->section->course->title }}
                            </p>
                            <p class="text-sm text-zinc-500 dark:text-zinc-400">
                                {{ $enrollment->section->instructor?->user?->name ?? 'Staff unassigned' }}
                                · {{ $enrollment->section->sectionSchedules->map(fn ($s) => $s->dayLabel().' '.$s->timeLabel())->implode(', ') ?: 'TBA' }}
                            </p>
                        </div>
                        <button
                            type="button"
                            class="shrink-0 rounded-lg border border-zinc-300 px-3 py-1.5 text-xs font-medium text-zinc-700 hover:bg-zinc-100 dark:border-zinc-700 dark:text-zinc-300 dark:hover:bg-zinc-800"
                            wire:click="drop({{ $enrollment->id }})"
                            wire:loading.attr="disabled"
                            wire:target="drop({{ $enrollment->id }})"
                            wire:key="drop-btn-{{ $enrollment->id }}"
                        >
                            Drop
                        </button>
                    </li>
                @endforeach
            </ul>
        @endif
    </div>
</div>
