<?php

use App\Actions\Academic\EnrollInSection;
use App\Models\AcademicTerm;
use App\Models\CourseSection;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\On;
use Livewire\Component;

new class extends Component {
    public string $search = '';

    public ?string $feedback = null;

    public ?string $error = null;

    /**
     * @return \Illuminate\Support\Collection<int, CourseSection>
     */
    public function openSections()
    {
        $student = auth()->user()?->studentProfile;

        if ($student === null) {
            return collect();
        }

        $currentTermId = AcademicTerm::query()->current()->value('id');

        return CourseSection::query()
            ->open()
            ->where('term_id', $currentTermId)
            ->where(function ($query) {
                $query->whereHas('course', fn ($q) => $q
                    ->where('code', 'like', "%{$this->search}%")
                    ->orWhere('title', 'like', "%{$this->search}%"))
                    ->orWhereHas('instructor', fn ($q) => $q->where('title', 'like', "%{$this->search}%"));
            })
            ->with('course', 'instructor.user', 'sectionSchedules')
            ->withCount(['enrollments' => fn ($q) => $q->where('status', 'active')])
            ->get()
            ->sortBy('course.code');
    }

    public function enroll(EnrollInSection $enrollInSection, int $sectionId): void
    {
        $this->reset('feedback', 'error');

        $student = auth()->user()?->studentProfile;

        abort_unless($student !== null, 403);

        $section = CourseSection::query()->whereKey($sectionId)->firstOrFail();

        try {
            $enrollInSection->handle($student, $section);
            $this->feedback = 'Enrolled in '.$section->fullCode().' successfully.';
        } catch (ValidationException $exception) {
            $this->error = $exception->errors()['enroll'][0] ?? 'Unable to enroll.';
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

<div class="rounded-2xl border border-zinc-200 bg-white shadow-sm dark:border-zinc-800 dark:bg-zinc-900" data-test="portal-open-sections">
    <div class="space-y-3 border-b border-zinc-200 px-6 py-4 dark:border-zinc-800">
        <div>
            <h2 class="flex items-center gap-2 text-base font-semibold text-zinc-900 dark:text-zinc-100">
                <flux:icon.book-open class="size-5 text-indigo-600 dark:text-indigo-400" />
                Open sections
            </h2>
            <p class="text-sm text-zinc-500 dark:text-zinc-400">Register in sections offered this term.</p>
        </div>
        <flux:input wire:model.live.debounce.300ms="search" placeholder="Search by code, title or instructor…" />
    </div>

    <div class="p-6">
        @if ($error)
            <p class="mb-4 rounded-lg bg-red-50 px-3 py-2 text-sm text-red-700 dark:bg-red-950/50 dark:text-red-300" wire:key="enroll-error-{{ md5($error) }}">{{ $error }}</p>
        @elseif ($feedback)
            <p class="mb-4 rounded-lg bg-emerald-50 px-3 py-2 text-sm text-emerald-700 dark:bg-emerald-950/50 dark:text-emerald-300" wire:key="enroll-feedback-{{ md5($feedback) }}">{{ $feedback }}</p>
        @endif

        @if ($this->openSections()->isEmpty())
            <p class="text-sm text-zinc-500 dark:text-zinc-400">No open sections match your search.</p>
        @else
            <ul class="space-y-4">
                @foreach ($this->openSections() as $section)
                    @php
                        $seatsTaken = $section->enrollments_count;
                        $seatsLeft = max(0, $section->capacity - $seatsTaken);
                        $isFull = $seatsLeft === 0;
                    @endphp
                    <li class="flex items-start justify-between gap-4 rounded-xl border border-zinc-200 p-4 dark:border-zinc-800" wire:key="section-{{ $section->id }}">
                        <div class="min-w-0">
                            <p class="font-medium text-zinc-900 dark:text-zinc-100">
                                {{ $section->course->code }} — {{ $section->course->title }}
                                <span class="text-zinc-400">{{ $section->fullCode() }}</span>
                            </p>
                            <p class="text-sm text-zinc-500 dark:text-zinc-400">
                                {{ $section->instructor?->user?->name ?? 'Staff unassigned' }}
                                · {{ $section->sectionSchedules->map(fn ($s) => $s->dayLabel().' '.$s->timeLabel())->implode(', ') ?: 'TBA' }}
                            </p>
                            <p class="text-sm {{ $isFull ? 'text-red-600 dark:text-red-400' : 'text-emerald-600 dark:text-emerald-400' }}">
                                {{ $isFull ? 'Full' : $seatsLeft.' of '.$section->capacity.' seats left' }}
                            </p>
                        </div>
                        <button
                            type="button"
                            @disabled($isFull)
                            class="shrink-0 rounded-lg bg-indigo-600 px-3 py-1.5 text-xs font-medium text-white hover:bg-indigo-500 disabled:pointer-events-none disabled:opacity-50"
                            wire:click="enroll({{ $section->id }})"
                            wire:loading.attr="disabled"
                            wire:target="enroll({{ $section->id }})"
                            wire:key="enroll-btn-{{ $section->id }}"
                        >
                            Enroll
                        </button>
                    </li>
                @endforeach
            </ul>
        @endif
    </div>
</div>
