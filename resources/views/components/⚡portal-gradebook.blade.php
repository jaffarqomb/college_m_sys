<?php

use App\Models\AcademicTerm;
use App\Models\Enrollment;
use App\Models\Student;
use App\Services\Academic\GradeService;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Component;

new class extends Component {
    /**
     * Active enrollments of the signed-in student this term.
     *
     * @return Collection<int, Enrollment>
     */
    #[Computed]
    public function rows(): Collection
    {
        $student = auth()->user()?->studentProfile;

        if ($student === null) {
            return collect();
        }

        $currentTermId = AcademicTerm::query()->current()->value('id');

        return $student->enrollments()
            ->active()
            ->whereHas('section', fn ($query) => $query->where('term_id', $currentTermId))
            ->with('section.course', 'section.sectionSchedules', 'grades.assessment', 'attendanceRecords.classSession')
            ->get()
            ->sortBy('section.course.code');
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

@php
    $gradeService = new App\Services\Academic\GradeService;
    $student = auth()->user()?->studentProfile;
    $term = App\Models\AcademicTerm::query()->current()->first();
@endphp

<div class="rounded-2xl border border-zinc-200 bg-white shadow-sm dark:border-zinc-800 dark:bg-zinc-900" data-test="portal-gradebook">
    <div class="flex items-center justify-between gap-3 border-b border-zinc-200 px-6 py-4 dark:border-zinc-800">
        <div>
            <h2 class="flex items-center gap-2 text-base font-semibold text-zinc-900 dark:text-zinc-100">
                <flux:icon.chart-bar-square class="size-5 text-indigo-600 dark:text-indigo-400" />
                My grades
            </h2>
            <p class="text-sm text-zinc-500 dark:text-zinc-400">{{ $term?->name ?? 'No active term' }}</p>
        </div>
        @if ($student instanceof App\Models\Student)
            <a href="{{ route('transcript', ['current_team' => auth()->user()->currentTeam?->slug]) }}"
               class="text-sm font-medium text-indigo-600 hover:text-indigo-500 dark:text-indigo-400"
               data-test="gradebook-transcript-link">
                Full transcript →
            </a>
        @endif
    </div>

    <div class="p-6">
        @if ($this->rows()->isEmpty())
            <p class="text-sm text-zinc-500 dark:text-zinc-400">You have no active enrollments this term.</p>
        @else
            <table class="w-full text-sm" data-test="portal-gradebook-table">
                <thead>
                    <tr class="text-left text-xs uppercase tracking-wide text-zinc-500 dark:text-zinc-400">
                        <th class="pb-2">Course</th>
                        <th class="pb-2">Credits</th>
                        <th class="pb-2">Attendance</th>
                        <th class="pb-2">Weighted grade</th>
                        <th class="pb-2">Letters</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-zinc-200 dark:divide-zinc-800">
                    @foreach ($this->rows() as $enrollment)
                        @php
                            $attendance = $gradeService->attendancePercent($enrollment);
                            $final = $gradeService->weightedFinalGrade($enrollment);
                        @endphp
                        <tr wire:key="gradebook-row-{{ $enrollment->id }}">
                            <td class="py-2 font-medium text-zinc-900 dark:text-zinc-100">
                                {{ $enrollment->section->fullCode() }}
                                <span class="block text-xs font-normal text-zinc-500 dark:text-zinc-400">{{ $enrollment->section->course->title }}</span>
                            </td>
                            <td class="py-2 text-zinc-600 dark:text-zinc-300">{{ $enrollment->section->course->credits }}</td>
                            <td class="py-2 text-zinc-600 dark:text-zinc-300" data-test="attendance-{{ $enrollment->id }}">
                                {{ $attendance !== null ? $attendance.'%' : '—' }}
                            </td>
                            <td class="py-2 text-zinc-600 dark:text-zinc-300" data-test="final-{{ $enrollment->id }}">
                                {{ $final !== null ? $final['percentage'].'%' : '—' }}
                            </td>
                            <td class="py-2">
                                @if ($final !== null)
                                    <span class="rounded-full bg-indigo-50 px-2.5 py-1 text-xs font-semibold text-indigo-700 dark:bg-indigo-950/50 dark:text-indigo-300">
                                        {{ $final['letter']->label() }} ({{ $final['points'] }} pts)
                                    </span>
                                @else
                                    <span class="text-zinc-400 dark:text-zinc-600">In progress</span>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
                <tfoot>
                    <tr class="text-sm font-semibold text-zinc-900 dark:text-zinc-100">
                        <td class="pt-3" colspan="4">Term GPA
                            <span class="ml-2 text-indigo-700 dark:text-indigo-300" data-test="term-gpa">
                                {{ $student instanceof App\Models\Student && $term !== null ? ($gradeService->termGpa($student, $term) ?? '—') : '—' }}
                            </span>
                        </td>
                        <td class="pt-3 text-right text-xs font-normal text-zinc-500 dark:text-zinc-400">
                            weighted by credit hours
                        </td>
                    </tr>
                </tfoot>
            </table>
        @endif
    </div>
</div>
