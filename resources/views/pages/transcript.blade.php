<x-layouts::app :title="__('Academic transcript')">
    @php
        $user = auth()->user();
        $student = $user->studentProfile;

        if ($student === null) {
            abort(403, 'No student profile is linked to this account.');
        }

        $gradeService = new App\Services\Academic\GradeService;
        $terms = $gradeService->transcript($student);
        $cumulative = $gradeService->cumulativeGpa($student);
    @endphp

    <div class="space-y-6" data-test="transcript">
        <div>
            <div class="flex flex-wrap items-end justify-between gap-3">
                <div>
                    <h1 class="text-xl font-semibold text-zinc-900 dark:text-zinc-100">Academic transcript</h1>
                    <p class="text-sm text-zinc-500 dark:text-zinc-400">
                        {{ $student->displayName() }} · {{ $student->student_number }}
                        @if ($student->program !== null)
                            · {{ $student->program->name }}
                        @endif
                    </p>
                </div>

                <a href="{{ route('dashboard', ['current_team' => $user->currentTeam?->slug]) }}"
                   class="text-sm font-medium text-indigo-600 hover:text-indigo-500 dark:text-indigo-400">
                    ← Back to dashboard
                </a>
            </div>
        </div>

        @if (count($terms) === 0)
            <div class="rounded-2xl border border-zinc-200 bg-white p-8 text-center dark:border-zinc-800 dark:bg-zinc-900">
                <flux:icon.academic-cap class="mx-auto size-10 text-zinc-400" />
                <h2 class="mt-3 text-lg font-semibold text-zinc-900 dark:text-zinc-100">No enrollments yet</h2>
                <p class="mt-1 text-sm text-zinc-500 dark:text-zinc-400">Enroll in sections from your dashboard to start building a transcript.</p>
            </div>
        @else
            @foreach ($terms as $entry)
                <div class="overflow-hidden rounded-2xl border border-zinc-200 bg-white shadow-sm dark:border-zinc-800 dark:bg-zinc-900" data-test="transcript-term-{{ $entry['term']->id }}">
                    <div class="flex flex-wrap items-center justify-between gap-3 border-b border-zinc-200 px-6 py-4 dark:border-zinc-800">
                        <div>
                            <h2 class="text-base font-semibold text-zinc-900 dark:text-zinc-100">{{ $entry['term']->name }}</h2>
                            <p class="text-sm text-zinc-500 dark:text-zinc-400">
                                {{ $entry['term']->starts_on?->format('M Y') ?? '' }}
                                @if ($entry['term']->ends_on !== null)
                                    – {{ $entry['term']->ends_on->format('M Y') }}
                                @endif
                            </p>
                        </div>
                        <div class="text-sm">
                            <span class="font-medium text-zinc-500 dark:text-zinc-400">Term GPA:</span>
                            <span class="font-semibold text-indigo-700 dark:text-indigo-300">{{ $entry['gpa'] ?? '—' }}</span>
                        </div>
                    </div>

                    <table class="w-full text-sm" data-test="transcript-table-{{ $entry['term']->id }}">
                        <thead>
                            <tr class="text-left text-xs uppercase tracking-wide text-zinc-500 dark:text-zinc-400">
                                <th class="px-6 pb-2 pt-3">Course</th>
                                <th class="pb-2 pt-3">Credits</th>
                                <th class="pb-2 pt-3">Weighted grade</th>
                                <th class="pb-2 pt-3">Letter</th>
                                <th class="pb-2 pt-3">Points</th>
                                <th class="px-6 pb-2 pt-3">Status</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-zinc-200 dark:divide-zinc-800">
                            @forelse ($entry['enrollments'] as $row)
                                <tr wire:key="transcript-row-{{ $row['section_id'] }}">
                                    <td class="px-6 py-3">
                                        <span class="font-medium text-zinc-900 dark:text-zinc-100">{{ $row['code'] }}</span>
                                        <span class="block text-xs text-zinc-500 dark:text-zinc-400">{{ $row['course'] }}</span>
                                    </td>
                                    <td class="py-3 text-zinc-600 dark:text-zinc-300">{{ $row['credits'] }}</td>
                                    <td class="py-3 text-zinc-600 dark:text-zinc-300">{{ $row['percentage'] !== null ? $row['percentage'].'%' : '—' }}</td>
                                    <td class="py-3">
                                        @if ($row['letter'] !== null)
                                            <span class="rounded-full bg-indigo-50 px-2.5 py-1 text-xs font-semibold text-indigo-700 dark:bg-indigo-950/50 dark:text-indigo-300">
                                                {{ $row['letter']->label() }}
                                            </span>
                                        @else
                                            <span class="text-zinc-400 dark:text-zinc-600">—</span>
                                        @endif
                                    </td>
                                    <td class="py-3 text-zinc-600 dark:text-zinc-300">{{ $row['points'] ?? '—' }}</td>
                                    <td class="px-6 py-3 text-zinc-600 dark:text-zinc-300">{{ ucfirst($row['status']) }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td class="px-6 py-3 text-zinc-500 dark:text-zinc-400" colspan="6">No courses recorded for this term.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            @endforeach

            <div class="rounded-2xl border border-indigo-200 bg-indigo-50 px-6 py-4 dark:border-indigo-900 dark:bg-indigo-950/40" data-test="transcript-cumulative">
                <p class="text-sm text-indigo-900 dark:text-indigo-200">
                    <span class="font-semibold">Cumulative GPA:</span>
                    <span class="text-lg font-bold">{{ $cumulative ?? '—' }}</span>
                    <span class="ml-2 text-xs text-indigo-700/70 dark:text-indigo-300/70">across all graded courses, weighted by credit hours</span>
                </p>
            </div>
        @endif
    </div>
</x-layouts::app>
