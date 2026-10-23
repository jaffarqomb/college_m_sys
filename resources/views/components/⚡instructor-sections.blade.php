<?php

use App\Models\AcademicTerm;
use Livewire\Component;

new class extends Component {
    /**
     * @return \Illuminate\Support\Collection<int, \App\Models\CourseSection>
     */
    public function mySections()
    {
        $instructor = auth()->user()?->instructorProfile;

        if ($instructor === null) {
            return collect();
        }

        $currentTermId = AcademicTerm::query()->current()->value('id');

        return $instructor->courseSections()
            ->where('term_id', $currentTermId)
            ->with('course', 'sectionSchedules')
            ->withCount(['enrollments' => fn ($q) => $q->where('status', 'active')])
            ->with(['enrollments' => fn ($q) => $q->where('status', 'active')->with('student.user')])
            ->get()
            ->sortBy('course.code');
    }

    public function placeholder(): string
    {
        return <<<'HTML'
            <div class="animate-pulse space-y-3 p-6">
                <div class="h-4 w-1/2 rounded bg-zinc-200 dark:bg-zinc-700"></div>
                <div class="h-4 w-3/4 rounded bg-zinc-200 dark:bg-zinc-700"></div>
            </div>
            HTML;
    }
}; ?>

<div class="space-y-6" data-test="instructor-sections">
    <div>
        <h1 class="text-xl font-semibold text-zinc-900 dark:text-zinc-100">My teaching load</h1>
        <p class="text-sm text-zinc-500 dark:text-zinc-400">{{ \App\Models\AcademicTerm::query()->current()->value('name') ?? 'No active term' }}</p>
    </div>

    @if ($this->mySections()->isEmpty())
        <p class="rounded-2xl border border-zinc-200 bg-white p-6 text-sm text-zinc-500 dark:border-zinc-800 dark:bg-zinc-900 dark:text-zinc-400">
            You have no assigned sections this term.
        </p>
    @else
        @foreach ($this->mySections() as $section)
            <div class="rounded-2xl border border-zinc-200 bg-white shadow-sm dark:border-zinc-800 dark:bg-zinc-900" wire:key="section-{{ $section->id }}">
                <div class="flex items-center justify-between gap-4 border-b border-zinc-200 px-6 py-4 dark:border-zinc-800">
                    <div>
                        <h2 class="text-base font-semibold text-zinc-900 dark:text-zinc-100">{{ $section->fullCode() }} — {{ $section->course->title }}</h2>
                        <p class="text-sm text-zinc-500 dark:text-zinc-400">
                            {{ $section->sectionSchedules->map(fn ($s) => $s->dayLabel().' '.$s->timeLabel())->implode(', ') ?: 'TBA' }}
                        </p>
                    </div>
                    <span class="rounded-full bg-indigo-50 px-3 py-1 text-xs font-medium text-indigo-700 dark:bg-indigo-950/50 dark:text-indigo-300">
                        {{ $section->enrollments_count }} / {{ $section->capacity }} enrolled
                    </span>
                </div>
                <div class="px-6 py-4">
                    @if ($section->enrollments->isEmpty())
                        <p class="text-sm text-zinc-500 dark:text-zinc-400">No students enrolled yet.</p>
                    @else
                        <details>
                            <summary class="cursor-pointer text-sm font-medium text-zinc-700 hover:text-zinc-900 dark:text-zinc-300 dark:hover:text-zinc-100">
                                Class roster ({{ $section->enrollments->count() }} students)
                            </summary>
                            <ul class="mt-3 grid gap-2 sm:grid-cols-2">
                                @foreach ($section->enrollments as $enrollment)
                                    <li class="rounded-lg border border-zinc-200 px-3 py-2 text-sm dark:border-zinc-800" wire:key="enrollment-{{ $enrollment->id }}">
                                        <span class="font-medium text-zinc-900 dark:text-zinc-100">{{ $enrollment->student->displayName() }}</span>
                                        <span class="text-zinc-500 dark:text-zinc-400">{{ $enrollment->student->student_number }}</span>
                                    </li>
                                @endforeach
                            </ul>
                        </details>
                    @endif
                </div>
            </div>
        @endforeach
    @endif
</div>
