<?php

namespace App\Filament\Admin\Pages;

use App\Actions\Timetable\PublishTimetableGeneration;
use App\Enums\TimetableGenerationStatus;
use App\Jobs\GenerateTimetable;
use App\Models\AcademicTerm;
use App\Models\CourseSection;
use App\Models\Room;
use App\Models\SectionSchedule;
use App\Models\TimetableGeneration;
use App\Services\Timetable\TimetableAiAssistant;
use App\Services\Timetable\TimetableGeneratorService;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TimePicker;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\EmbeddedTable;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use RuntimeException;
use UnitEnum;

/**
 * AI timetable builder: generate a scheduling draft for a term, review the
 * placement grid, adjust single-slot sections with revalidation, then publish
 * the draft into SectionSchedule rows. Local Ollama (via OllamaService) adds
 * advisory-only draft reviews and conflict-fix suggestions.
 */
class TimetableBuilder extends Page implements HasTable
{
    use InteractsWithTable;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCalendarDays;

    protected static string|UnitEnum|null $navigationGroup = 'Academic Structure';

    protected static ?int $navigationSort = 7;

    protected static ?string $navigationLabel = 'AI Timetable';

    protected static ?string $title = 'AI Timetable';

    protected string $view = 'filament.admin.pages.timetable-builder';

    /** Latest AI analysis from Ollama, displayed until dismissed. */
    public ?string $aiAnalysis = null;

    /** Which prompt produced the current analysis: "review" or "conflicts". */
    public string $aiAnalysisKind = 'review';

    public static function canAccess(): bool
    {
        $user = auth()->user();

        return $user !== null && $user->can('viewAny', TimetableGeneration::class);
    }

    public function content(Schema $schema): Schema
    {
        return $schema->components([
            View::make('filament.admin.timetable-status')
                ->viewData([
                    'generation' => $this->currentGeneration(),
                    'term' => $this->builderTerm(),
                ]),
            EmbeddedTable::make(),
        ]);
    }

    /**
     * The generation currently managed by the builder (the latest overall).
     */
    public function currentGeneration(): ?TimetableGeneration
    {
        return TimetableGeneration::query()->latest('id')->first();
    }

    /**
     * Term the builder is working on: the latest generation's term, else the
     * current term.
     */
    public function builderTerm(): ?AcademicTerm
    {
        $generation = $this->currentGeneration();

        $generation = $this->currentGeneration();

        if ($generation !== null) {
            return $generation->term()->first();
        }

        return AcademicTerm::query()->current()->first();
    }

    /**
     * Draft placements keyed by section id.
     *
     * @return array<int, array{section_id: int, code: string, course: string, instructor: string|null, fixed: bool, slots: list<array{day: int, start: string, end: string, room_id: int|null}>}>
     */
    public function placementsBySection(): array
    {
        $generation = $this->currentGeneration();

        if ($generation === null) {
            return [];
        }

        if ($generation->status !== TimetableGenerationStatus::Draft && $generation->status !== TimetableGenerationStatus::Published) {
            return [];
        }

        return collect($generation->assignments)
            ->mapWithKeys(fn (array $assignment): array => [
                (int) $assignment['section_id'] => $assignment,
            ])
            ->all();
    }

    public function table(Table $table): Table
    {
        $term = $this->builderTerm();

        $isGenerating = $this->currentGeneration()?->status === TimetableGenerationStatus::Generating;

        $query = CourseSection::query()->orderBy('course_id')->orderBy('section_number');

        if ($term !== null) {
            $query->where('term_id', $term->id);
        }

        return $table
            ->query($query)
            ->columns([
                TextColumn::make('full_code')
                    ->label('Course')
                    ->state(fn (CourseSection $record): string => $record->fullCode())
                    ->description(fn (CourseSection $record): string => (string) $record->course->title)
                    ->searchable(false)
                    ->sortable(false),
                TextColumn::make('instructor_name')
                    ->label('Instructor')
                    ->state(fn (CourseSection $record): string => $record->instructor?->user->name ?? 'Unassigned')
                    ->sortable(false),
                TextColumn::make('draft_schedule')
                    ->label('Draft schedule')
                    ->state(fn (CourseSection $record): array => $this->scheduleLinesFor($record))
                    ->listWithLineBreaks()
                    ->placeholder('Not placed in the draft')
                    ->sortable(false),
                TextColumn::make('draft_status')
                    ->label('Status')
                    ->state(fn (CourseSection $record): string => $this->rowStatusFor($record))
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'Fixed' => 'info',
                        'Placed' => 'success',
                        'Conflict' => 'warning',
                        default => 'danger',
                    })
                    ->sortable(false),
            ])
            ->recordActions([
                $this->adjustAction(),
            ])
            ->poll($isGenerating ? '5s' : null)
            ->paginated(false);
    }

    /**
     * @return array<int, string>
     */
    protected function scheduleLinesFor(CourseSection $record): array
    {
        $placement = $this->placementsBySection()[$record->id] ?? null;

        if ($placement === null) {
            return [];
        }

        $rooms = Room::query()->get()->keyBy('id');

        return collect($placement['slots'])
            ->map(fn (array $slot): string => sprintf(
                '%s · %s–%s · %s',
                SectionSchedule::DAYS[(int) $slot['day']] ?? 'Unknown',
                (string) $slot['start'],
                (string) $slot['end'],
                $slot['room_id'] !== null ? ($rooms[(int) $slot['room_id']]->code ?? '?') : 'No room',
            ))
            ->all();
    }

    protected function rowStatusFor(CourseSection $record): string
    {
        $placement = $this->placementsBySection()[$record->id] ?? null;

        if ($placement === null) {
            return 'Conflict';
        }

        if ($placement['fixed']) {
            return 'Fixed';
        }

        return 'Placed';
    }

    protected function canAdjustRow(CourseSection $record): bool
    {
        $generation = $this->currentGeneration();

        if ($generation === null || $generation->status !== TimetableGenerationStatus::Draft) {
            return false;
        }

        $placement = $this->placementsBySection()[$record->id] ?? null;

        if ($placement === null || $placement['fixed']) {
            return false;
        }

        return count($placement['slots']) === 1;
    }

    protected function adjustAction(): Action
    {
        return Action::make('adjust')
            ->label('Adjust slot')
            ->icon(Heroicon::OutlinedClock)
            ->color('gray')
            ->authorize(function (): bool {
                $user = auth()->user();

                return $user !== null && ($user->isSystemAdmin() || $user->isRegistrar());
            })
            ->visible(fn (CourseSection $record): bool => $this->canAdjustRow($record))
            ->schema([
                Select::make('day')
                    ->label('Day')
                    ->options(SectionSchedule::DAYS)
                    ->required(),
                TimePicker::make('start')
                    ->label('Start time')
                    ->seconds(false)
                    ->required(),
                TimePicker::make('end')
                    ->label('End time')
                    ->seconds(false)
                    ->required(),
                Select::make('room_id')
                    ->label('Room')
                    ->options(fn (): array => Room::query()
                        ->orderBy('code')
                        ->get()
                        ->mapWithKeys(fn (Room $room): array => [$room->id => "{$room->code} ({$room->capacity} seats)"])
                        ->all())
                    ->required(),
            ])
            ->mountUsing(function (CourseSection $record, Schema $form): void {
                $placement = $this->placementsBySection()[$record->id] ?? null;
                $slot = $placement['slots'][0] ?? null;

                $form->fill([
                    'day' => $slot['day'] ?? null,
                    'start' => $slot['start'] ?? null,
                    'end' => $slot['end'] ?? null,
                    'room_id' => $slot['room_id'] ?? null,
                ]);
            })
            ->action(function (array $data, CourseSection $record): void {
                $generation = $this->currentGeneration();

                if ($generation === null || $generation->status !== TimetableGenerationStatus::Draft) {
                    Notification::make()
                        ->title('The draft is no longer adjustable.')
                        ->danger()
                        ->send();

                    return;
                }

                if ($data['end'] <= $data['start']) {
                    Notification::make()
                        ->title('The end time must be after the start time.')
                        ->danger()
                        ->send();

                    return;
                }

                $newSlots = [
                    [
                        'day' => (int) $data['day'],
                        'start' => (string) $data['start'],
                        'end' => (string) $data['end'],
                        'room_id' => (int) $data['room_id'],
                    ],
                ];

                $solver = app(TimetableGeneratorService::class);

                $violations = $solver->validateAdjustment(
                    $generation->term,
                    $record->id,
                    $newSlots,
                    $generation->assignments,
                    $generation->settings,
                );

                if ($violations !== []) {
                    Notification::make()
                        ->title('The adjustment violates timetable constraints.')
                        ->body(implode(' ', $violations))
                        ->danger()
                        ->send();

                    return;
                }

                $assignments = collect($generation->assignments)
                    ->map(fn (array $assignment): array => (int) $assignment['section_id'] === $record->id
                        ? [...$assignment, 'fixed' => false, 'slots' => $newSlots]
                        : $assignment)
                    ->all();

                $generation->update([
                    'assignments' => $assignments,
                ]);

                Notification::make()
                    ->title("Adjusted {$record->fullCode()} in the draft.")
                    ->success()
                    ->send();
            });
    }

    /**
     * @return array<int, Action>
     */
    protected function getHeaderActions(): array
    {
        return [
            $this->generateAction(),
            $this->aiReviewAction(),
            $this->aiConflictFixesAction(),
            $this->publishAction(),
        ];
    }

    /**
     * Ask the local Ollama model to critique the generated draft. Advisory
     * only — every suggested move must pass solver validation before use.
     */
    protected function aiReviewAction(): Action
    {
        return Action::make('aiReview')
            ->label('AI review')
            ->icon(Heroicon::OutlinedChatBubbleLeftRight)
            ->color('gray')
            ->authorize('viewAny', TimetableGeneration::class)
            ->visible(fn (): bool => $this->currentGeneration()?->status === TimetableGenerationStatus::Draft)
            ->action(function (): void {
                $generation = $this->currentGeneration();

                if ($generation === null || $generation->status !== TimetableGenerationStatus::Draft) {
                    return;
                }

                $this->askAi(
                    'review',
                    fn (TimetableAiAssistant $assistant): string => $assistant->reviewDraft(
                        $generation->term,
                        [
                            'assignments' => $generation->assignments,
                            'conflicts' => $generation->conflicts,
                            'fitness' => $generation->fitness,
                        ],
                        $generation->settings,
                    ),
                    'AI review ready.',
                );
            });
    }

    /**
     * Ask the local Ollama model for repair suggestions for the sections the
     * solver could not place.
     */
    protected function aiConflictFixesAction(): Action
    {
        return Action::make('aiConflictFixes')
            ->label('AI conflict fixes')
            ->icon(Heroicon::OutlinedLightBulb)
            ->color('warning')
            ->authorize('viewAny', TimetableGeneration::class)
            ->visible(function (): bool {
                $generation = $this->currentGeneration();

                return $generation !== null
                    && $generation->status === TimetableGenerationStatus::Draft
                    && collect($generation->conflicts)->isNotEmpty();
            })
            ->action(function (): void {
                $generation = $this->currentGeneration();

                if ($generation === null || $generation->status !== TimetableGenerationStatus::Draft) {
                    return;
                }

                $this->askAi(
                    'conflicts',
                    fn (TimetableAiAssistant $assistant): string => $assistant->suggestConflictFixes(
                        $generation->term,
                        $generation->conflicts,
                        $generation->settings,
                    ),
                    'AI conflict-fix suggestions ready.',
                );
            });
    }

    /**
     * Run an AI prompt against the current analysis state, surfacing Ollama
     * failures as a warning notification instead of an error page.
     *
     * @param  callable(TimetableAiAssistant): string  $prompt
     */
    protected function askAi(string $kind, callable $prompt, string $readyTitle): void
    {
        try {
            $analysis = $prompt(app(TimetableAiAssistant::class));
        } catch (RuntimeException $exception) {
            Notification::make()
                ->title('The AI assistant is unavailable.')
                ->body($exception->getMessage())
                ->warning()
                ->send();

            return;
        }

        $this->aiAnalysis = $analysis;
        $this->aiAnalysisKind = $kind;

        Notification::make()
            ->title($readyTitle)
            ->body('Suggestions are advisory only — apply them with the Adjust slot action.')
            ->success()
            ->send();
    }

    protected function generateAction(): Action
    {
        return Action::make('generate')
            ->label('Generate draft')
            ->icon(Heroicon::OutlinedSparkles)
            ->color('primary')
            ->authorize('create', TimetableGeneration::class)
            ->schema([
                Select::make('term_id')
                    ->label('Term')
                    ->options(fn (): array => AcademicTerm::query()
                        ->orderByDesc('start_date')
                        ->get()
                        ->mapWithKeys(fn (AcademicTerm $term): array => $term->is_current
                            ? [$term->id => $term->name.' (current)']
                            : [$term->id => $term->name])
                        ->all())
                    ->default(fn (): ?int => AcademicTerm::query()->current()->first()?->id)
                    ->required()
                    ->live(),
                CheckboxList::make('days')
                    ->label('Preferred days')
                    ->options(SectionSchedule::DAYS)
                    ->default([1, 2, 3, 4, 5])
                    ->required()
                    ->columns(3),
                TimePicker::make('day_start')
                    ->label('Day starts')
                    ->seconds(false)
                    ->default('08:00')
                    ->required(),
                TimePicker::make('day_end')
                    ->label('Day ends')
                    ->seconds(false)
                    ->default('18:00')
                    ->required(),
                Select::make('granularity')
                    ->label('Slot granularity')
                    ->options([15 => 'Every 15 minutes', 30 => 'Every 30 minutes', 60 => 'Every hour'])
                    ->default(30)
                    ->required(),
                Select::make('max_daily_hours')
                    ->label('Max hours per day')
                    ->options(collect(range(1, 10))
                        ->mapWithKeys(fn (int $hours): array => [$hours => (string) $hours])
                        ->all())
                    ->default(5)
                    ->required(),
                Select::make('buffer_minutes')
                    ->label('Transition buffer between sessions')
                    ->options([0 => 'None', 5 => '5 minutes', 10 => '10 minutes', 15 => '15 minutes', 30 => '30 minutes'])
                    ->default(15)
                    ->required(),
                Toggle::make('regenerate_existing')
                    ->label('Regenerate existing schedules')
                    ->default(false)
                    ->helperText('Sections that already have a weekly schedule are kept as fixed constraints unless enabled.'),
            ])
            ->action(function (array $data): void {
                $term = AcademicTerm::query()->whereKey($data['term_id'])->first();

                if ($term === null) {
                    Notification::make()
                        ->title('The selected term no longer exists.')
                        ->danger()
                        ->send();

                    return;
                }

                $generation = TimetableGeneration::query()->create([
                    'term_id' => $term->id,
                    'user_id' => auth()->id(),
                    'status' => TimetableGenerationStatus::Generating,
                    'settings' => [
                        'days' => array_map(intval(...), (array) $data['days']),
                        'day_start' => (string) $data['day_start'],
                        'day_end' => (string) $data['day_end'],
                        'granularity' => (int) $data['granularity'],
                        'max_daily_hours' => (int) $data['max_daily_hours'],
                        'buffer_minutes' => (int) $data['buffer_minutes'],
                        'regenerate_existing' => (bool) $data['regenerate_existing'],
                    ],
                    'assignments' => [],
                    'conflicts' => [],
                ]);

                GenerateTimetable::dispatch($generation);

                Notification::make()
                    ->title('Timetable generation queued.')
                    ->body('The draft for '.$term->name.' will appear here shortly.')
                    ->success()
                    ->send();
            });
    }

    protected function publishAction(): Action
    {
        return Action::make('publish')
            ->label('Publish schedule')
            ->icon(Heroicon::OutlinedCheckBadge)
            ->color('success')
            ->authorize('publishAny', TimetableGeneration::class)
            ->visible(fn (): bool => $this->currentGeneration()?->status === TimetableGenerationStatus::Draft)
            ->requiresConfirmation()
            ->modalHeading('Publish timetable')
            ->modalDescription('Publishing replaces the weekly schedule of every section included in the draft. Sections left out of the draft (conflicts) keep their existing schedules. This cannot be undone.')
            ->modalSubmitActionLabel('Publish')
            ->action(function (): void {
                $generation = $this->currentGeneration();

                if ($generation === null) {
                    return;
                }

                $replaced = app(PublishTimetableGeneration::class)->handle($generation);

                Notification::make()
                    ->title('Timetable published.')
                    ->body(count($replaced).' '.str('section schedule')->plural(count($replaced)).' replaced.')
                    ->success()
                    ->send();
            });
    }
}
