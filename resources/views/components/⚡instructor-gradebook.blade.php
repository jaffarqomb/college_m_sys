<?php

use App\Actions\Academic\SaveSectionScores;
use App\Models\AcademicTerm;
use App\Models\Assessment;
use App\Models\CourseSection;
use App\Models\Enrollment;
use App\Services\Academic\GradeService;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Computed;
use Livewire\Component;

new class extends Component {
    public ?int $sectionId = null;

    /** @var array<int|string, array<int|string, string|null>> */
    public array $scoresByAssessment = [];

    public ?string $feedback = null;

    public ?string $error = null;

    public function mount(): void
    {
        $this->sectionId = $this->teachingSections()->first()?->id;
        $this->loadGrid();
    }

    public function updatedSectionId(): void
    {
        $this->loadGrid();
    }

    /**
     * @return Collection<int, CourseSection>
     */
    #[Computed]
    public function teachingSections(): Collection
    {
        $instructor = auth()->user()?->instructorProfile;

        if ($instructor === null) {
            return collect();
        }

        $currentTermId = AcademicTerm::query()->current()->value('id');

        return $instructor->courseSections()
            ->where('term_id', $currentTermId)
            ->orderBy('course_id')
            ->with('course')
            ->get();
    }

    /**
     * Active enrollments for the selected section.
     *
     * @return Collection<int, Enrollment>
     */
    #[Computed]
    public function roster(): Collection
    {
        $section = $this->gradingSection();

        if ($section === null) {
            return collect();
        }

        return $section->enrollments()
            ->where('status', 'active')
            ->with('student.user', 'grades.assessment')
            ->orderBy('id')
            ->get();
    }

    /**
     * Assessments of the selected section.
     *
     * @return Collection<int, Assessment>
     */
    #[Computed]
    public function assessments(): Collection
    {
        $section = $this->gradingSection();

        if ($section === null) {
            return collect();
        }

        return $section->assessments()
            ->orderBy('id')
            ->get();
    }

    /**
     * Persist every filled score cell through the domain action.
     */
    public function save(): void
    {
        $section = $this->gradingSection();

        if ($section === null) {
            $this->error = 'Choose one of your sections first.';

            return;
        }

        try {
            $action = new SaveSectionScores;
            $action->handle($section, $this->scoresByAssessment);

            $this->loadGrid();
            $this->feedback = 'Scores saved for '.$section->fullCode().'.';
        } catch (ValidationException $exception) {
            // Keep the entered grid intact so the error can be corrected in place.
            $this->feedback = null;
            $this->error = $exception->errors()['scores'][0] ?? 'Unable to save scores.';
        }
    }

    /**
     * Composite grade summaries for the summary column.
     */
    public function summaryFor(Enrollment $enrollment): string
    {
        $service = new GradeService;

        $final = $service->weightedFinalGrade($enrollment);

        if ($final === null) {
            return '—';
        }

        return $final['percentage'].'% ('.$final['letter']->label().' · '.$final['points'].' GPA pts)';
    }

    public function loadGrid(): void
    {
        $this->feedback = null;
        $this->error = null;
        $this->scoresByAssessment = [];

        foreach ($this->assessments() as $assessment) {
            $this->scoresByAssessment[$assessment->id] = [];

            foreach ($this->roster() as $enrollment) {
                $grade = $enrollment->grades->firstWhere('assessment_id', $assessment->id);

                $this->scoresByAssessment[$assessment->id][$enrollment->id] = $grade?->score !== null ? (string) $grade->score : '';
            }
        }
    }

    private function gradingSection(): ?CourseSection
    {
        if ($this->sectionId === null) {
            return null;
        }

        return $this->teachingSections()->firstWhere('id', $this->sectionId);
    }

    public function placeholder(): string
    {
        return <<<'HTML'
            <div class="animate-pulse space-y-3 p-6">
                <div class="h-4 w-1/3 rounded bg-zinc-200 dark:bg-zinc-700"></div>
                <div class="h-4 w-2/3 rounded bg-zinc-200 dark:bg-zinc-700"></div>
            </div>
            HTML;
    }
}; ?>

<div class="rounded-2xl border border-zinc-200 bg-white shadow-sm dark:border-zinc-800 dark:bg-zinc-900" data-test="instructor-gradebook">
    <div class="flex items-center justify-between gap-3 border-b border-zinc-200 px-6 py-4 dark:border-zinc-800">
        <div>
            <h2 class="flex items-center gap-2 text-base font-semibold text-zinc-900 dark:text-zinc-100">
                <flux:icon.chart-bar class="size-5 text-indigo-600 dark:text-indigo-400" />
                Gradebook
            </h2>
            <p class="text-sm text-zinc-500 dark:text-zinc-400">Enter assessment scores per student in one table.</p>
        </div>
    </div>

    <div class="p-6">
        @if ($this->teachingSections()->isEmpty())
            <p class="text-sm text-zinc-500 dark:text-zinc-400">You have no sections in the current term.</p>
        @else
            <label class="flex flex-col gap-1 text-sm font-medium text-zinc-700 dark:text-zinc-300">
                Section
                <select
                    class="max-w-sm rounded-lg border border-zinc-300 bg-white px-3 py-2 text-sm text-zinc-900 dark:border-zinc-700 dark:bg-zinc-900 dark:text-zinc-100"
                    wire:model="sectionId"
                    data-test="gradebook-section-select"
                >
                    @foreach ($this->teachingSections() as $option)
                        <option value="{{ $option->id }}">{{ $option->fullCode() }} — {{ $option->course->title }}</option>
                    @endforeach
                </select>
            </label>

            @if ($error)
                <p class="mt-4 rounded-lg bg-red-50 px-3 py-2 text-sm text-red-700 dark:bg-red-950/50 dark:text-red-300" wire:key="gradebook-error-{{ md5($error) }}">{{ $error }}</p>
            @elseif ($feedback)
                <p class="mt-4 rounded-lg bg-emerald-50 px-3 py-2 text-sm text-emerald-700 dark:bg-emerald-950/50 dark:text-emerald-300" wire:key="gradebook-feedback-{{ md5($feedback) }}">{{ $feedback }}</p>
            @endif

            @if ($this->assessments()->isEmpty())
                <p class="mt-5 text-sm text-zinc-500 dark:text-zinc-400">
                    No assessments exist for this section yet. The registrar can create them in the admin panel.
                </p>
            @elseif ($this->roster()->isEmpty())
                <p class="mt-5 text-sm text-zinc-500 dark:text-zinc-400">No active enrollments in this section.</p>
            @else
                <div class="mt-5 overflow-x-auto">
                    <table class="w-full text-sm" data-test="gradebook-table">
                        <thead>
                            <tr class="text-left text-xs uppercase tracking-wide text-zinc-500 dark:text-zinc-400">
                                <th class="pb-2 pr-4">Student</th>
                                @foreach ($this->assessments() as $assessment)
                                    <th class="pb-2 pr-3" wire:key="gb-assessment-{{ $assessment->id }}" title="Points out of {{ $assessment->max_score }}">
                                        {{ $assessment->title }}<span class="block font-normal normal-case text-zinc-400">/ {{ $assessment->max_score }} · w {{ $assessment->weight }}</span>
                                    </th>
                                @endforeach
                                <th class="pb-2">Composite</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-zinc-200 dark:divide-zinc-800">
                            @foreach ($this->roster() as $enrollment)
                                <tr wire:key="gb-row-{{ $enrollment->id }}">
                                    <td class="py-2 pr-4">
                                        <span class="font-medium text-zinc-900 dark:text-zinc-100">{{ $enrollment->student->displayName() }}</span>
                                        <span class="block text-xs text-zinc-500 dark:text-zinc-400">{{ $enrollment->student->student_number }}</span>
                                    </td>
                                    @foreach ($this->assessments() as $assessment)
                                        <td class="py-2 pr-3" wire:key="gb-cell-{{ $enrollment->id }}-{{ $assessment->id }}">
                                            <input
                                                type="number"
                                                min="0"
                                                max="{{ $assessment->max_score }}"
                                                step="0.5"
                                                class="w-20 rounded-lg border border-zinc-300 bg-white px-2 py-1.5 text-sm text-zinc-900 dark:border-zinc-700 dark:bg-zinc-900 dark:text-zinc-100"
                                                wire:model="scoresByAssessment.{{ $assessment->id }}.{{ $enrollment->id }}"
                                                data-test="score-{{ $assessment->id }}-{{ $enrollment->id }}"
                                            />
                                        </td>
                                    @endforeach
                                    <td class="py-2 text-zinc-600 dark:text-zinc-300" data-test="summary-{{ $enrollment->id }}">
                                        {{ $this->summaryFor($enrollment) }}
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <div class="mt-4 flex items-center gap-3">
                    <button
                        type="button"
                        class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-500"
                        wire:click="save"
                        wire:loading.attr="disabled"
                        wire:target="save"
                        data-test="gradebook-save"
                    >
                        Save scores
                    </button>
                    <span class="text-xs text-zinc-500 dark:text-zinc-400" wire:loading wire:target="save">Saving…</span>
                </div>
            @endif
        @endif
    </div>
</div>
