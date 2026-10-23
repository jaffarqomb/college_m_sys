<?php

use App\Actions\Academic\MarkAttendance;
use App\Enums\AttendanceStatus;
use App\Models\AcademicTerm;
use App\Models\ClassSession;
use App\Models\CourseSection;
use App\Models\Enrollment;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Computed;
use Livewire\Component;

new class extends Component {
    public ?int $sectionId = null;

    public string $date = '';

    /** @var array<int|string, string> */
    public array $statuses = [];

    public ?string $feedback = null;

    public ?string $error = null;

    public function mount(): void
    {
        $this->date = now()->toDateString();
        $this->sectionId = $this->mySections()->first()?->id;
        $this->loadSession();
    }

    public function updatedSectionId(): void
    {
        $this->loadSession();
    }

    public function updatedDate(): void
    {
        $this->loadSession();
    }

    /**
     * Sections of the instructor's own courses in the current term.
     *
     * @return Collection<int, CourseSection>
     */
    #[Computed]
    public function mySections(): Collection
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
        $section = $this->markingSection();

        if ($section === null) {
            return collect();
        }

        return $section->enrollments()
            ->where('status', 'active')
            ->with('student.user')
            ->orderBy('id')
            ->get();
    }

    /**
     * Persist the submitted status map through the domain action.
     */
    public function save(): void
    {
        $section = $this->markingSection();

        if ($section === null) {
            $this->error = 'Choose one of your sections first.';

            return;
        }

        try {
            $action = new MarkAttendance;
            $action->handle($section, $this->date, $this->statuses);

            $this->feedback = 'Attendance saved for '.$section->fullCode().' on '.now()->parse($this->date)->format('M j, Y').'.';

            $this->loadSession();
        } catch (ValidationException $exception) {
            $this->error = $exception->errors()['attendance'][0] ?? 'Unable to save attendance.';
        }
    }

    /**
     * Bulk-set every roster row to the given status.
     */
    public function markAll(string $status): void
    {
        $stone = AttendanceStatus::tryFrom($status);

        if ($stone === null) {
            return;
        }

        foreach ($this->roster() as $enrollment) {
            $this->statuses[$enrollment->id] = $stone->value;
        }
    }

    public function loadSession(): void
    {
        $this->feedback = null;
        $this->error = null;

        $session = $this->existingSession();

        $this->statuses = [];

        foreach ($this->roster() as $enrollment) {
            $record = $session?->attendanceRecords->firstWhere('enrollment_id', $enrollment->id);

            $this->statuses[$enrollment->id] = $record?->status->value ?? AttendanceStatus::Present->value;
        }
    }

    private function existingSession(): ?ClassSession
    {
        $section = $this->markingSection();

        if ($section === null) {
            return null;
        }

        return ClassSession::query()
            ->with('attendanceRecords')
            ->where('section_id', $section->id)
            ->whereDate('session_date', $this->date)
            ->first();
    }

    private function markingSection(): ?CourseSection
    {
        if ($this->sectionId === null) {
            return null;
        }

        return $this->mySections()->firstWhere('id', $this->sectionId);
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

<div class="rounded-2xl border border-zinc-200 bg-white shadow-sm dark:border-zinc-800 dark:bg-zinc-900" data-test="attendance-marking">
    <div class="flex items-center justify-between gap-3 border-b border-zinc-200 px-6 py-4 dark:border-zinc-800">
        <div>
            <h2 class="flex items-center gap-2 text-base font-semibold text-zinc-900 dark:text-zinc-100">
                <flux:icon.clipboard-document-check class="size-5 text-indigo-600 dark:text-indigo-400" />
                Attendance marking
            </h2>
            <p class="text-sm text-zinc-500 dark:text-zinc-400">Mark every student for a session in one pass.</p>
        </div>
    </div>

    <div class="p-6">
        @if ($this->mySections()->isEmpty())
            <p class="text-sm text-zinc-500 dark:text-zinc-400">You have no sections in the current term.</p>
        @else
            <div class="flex flex-wrap items-end gap-4">
                <label class="flex flex-col gap-1 text-sm font-medium text-zinc-700 dark:text-zinc-300">
                    Section
                    <select
                        class="rounded-lg border border-zinc-300 bg-white px-3 py-2 text-sm text-zinc-900 dark:border-zinc-700 dark:bg-zinc-900 dark:text-zinc-100"
                        wire:model="sectionId"
                        data-test="attendance-section-select"
                    >
                        @foreach ($this->mySections() as $option)
                            <option value="{{ $option->id }}">{{ $option->fullCode() }} — {{ $option->course->title }}</option>
                        @endforeach
                    </select>
                </label>

                <label class="flex flex-col gap-1 text-sm font-medium text-zinc-700 dark:text-zinc-300">
                    Date
                    <input
                        type="date"
                        class="rounded-lg border border-zinc-300 bg-white px-3 py-2 text-sm text-zinc-900 dark:border-zinc-700 dark:bg-zinc-900 dark:text-zinc-100"
                        wire:model="date"
                        data-test="attendance-date-input"
                    />
                </label>
            </div>

            @if ($error)
                <p class="mt-4 rounded-lg bg-red-50 px-3 py-2 text-sm text-red-700 dark:bg-red-950/50 dark:text-red-300" wire:key="attendance-error-{{ md5($error) }}">{{ $error }}</p>
            @elseif ($feedback)
                <p class="mt-4 rounded-lg bg-emerald-50 px-3 py-2 text-sm text-emerald-700 dark:bg-emerald-950/50 dark:text-emerald-300" wire:key="attendance-feedback-{{ md5($feedback) }}">{{ $feedback }}</p>
            @endif

            @if ($this->roster()->isEmpty())
                <p class="mt-4 text-sm text-zinc-500 dark:text-zinc-400">No active enrollments in this section.</p>
            @else
                <div class="mt-5 flex flex-wrap items-center gap-2 text-xs font-medium">
                    <span class="text-zinc-500 dark:text-zinc-400">Mark all:</span>
                    @foreach (\App\Enums\AttendanceStatus::cases() as $status)
                        <button
                            type="button"
                            class="rounded-lg border border-zinc-300 px-2.5 py-1 text-zinc-700 hover:bg-zinc-100 dark:border-zinc-700 dark:text-zinc-300 dark:hover:bg-zinc-800"
                            wire:click="markAll('{{ $status->value }}')"
                            wire:key="attendance-mark-all-{{ $status->value }}"
                        >
                            {{ $status->label() }}
                        </button>
                    @endforeach
                </div>

                <table class="mt-3 w-full text-sm" data-test="attendance-table">
                    <thead>
                        <tr class="text-left text-xs uppercase tracking-wide text-zinc-500 dark:text-zinc-400">
                            <th class="pb-2">Student</th>
                            <th class="pb-2">Number</th>
                            <th class="pb-2 w-44">Status</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-zinc-200 dark:divide-zinc-800">
                        @foreach ($this->roster() as $enrollment)
                            <tr wire:key="attendance-row-{{ $enrollment->id }}">
                                <td class="py-2 font-medium text-zinc-900 dark:text-zinc-100">{{ $enrollment->student->displayName() }}</td>
                                <td class="py-2 text-zinc-500 dark:text-zinc-400">{{ $enrollment->student->student_number }}</td>
                                <td class="py-2">
                                    <select
                                        class="w-full rounded-lg border border-zinc-300 bg-white px-2 py-1.5 text-sm text-zinc-900 dark:border-zinc-700 dark:bg-zinc-900 dark:text-zinc-100"
                                        wire:model="statuses.{{ $enrollment->id }}"
                                        data-test="attendance-status-{{ $enrollment->id }}"
                                    >
                                        @foreach (\App\Enums\AttendanceStatus::cases() as $status)
                                            <option value="{{ $status->value }}">{{ $status->label() }}</option>
                                        @endforeach
                                    </select>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>

                <div class="mt-4 flex items-center gap-3">
                    <button
                        type="button"
                        class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-500"
                        wire:click="save"
                        wire:loading.attr="disabled"
                        wire:target="save"
                        data-test="attendance-save"
                    >
                        Save attendance
                    </button>
                    <span class="text-xs text-zinc-500 dark:text-zinc-400" wire:loading wire:target="save">Saving…</span>
                </div>
            @endif
        @endif
    </div>
</div>
