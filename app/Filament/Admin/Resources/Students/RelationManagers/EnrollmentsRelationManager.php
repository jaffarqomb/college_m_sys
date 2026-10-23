<?php

namespace App\Filament\Admin\Resources\Students\RelationManagers;

use App\Actions\Academic\EnrollInSection;
use App\Models\AcademicTerm;
use App\Models\CourseSection;
use App\Models\Enrollment;
use App\Models\Student;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Support\Enums\Operation;
use Filament\Support\Exceptions\Halt;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Validation\ValidationException;

class EnrollmentsRelationManager extends RelationManager
{
    protected static string $relationship = 'enrollments';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('section_id')
                    ->label('Section')
                    ->options(fn (): array => static::sectionOptions())
                    ->searchable()
                    ->required()
                    ->visibleOn(Operation::Create),

                Select::make('status')
                    ->label('Status')
                    ->options([
                        'active' => 'Active',
                        'completed' => 'Completed',
                        'dropped' => 'Dropped',
                    ])
                    ->default('active')
                    ->required()
                    ->visibleOn(Operation::Edit),

                DatePicker::make('dropped_at')
                    ->label('Dropped at')
                    ->visibleOn(Operation::Edit),
            ]);
    }

    /**
     * Section options for the current term, annotated with the section
     * status and remaining seats. Business rules (capacity, prerequisites,
     * deadlines, schedule conflicts) are still enforced at creation time.
     *
     * @return array<int, string>
     */
    public static function sectionOptions(): array
    {
        $term = AcademicTerm::query()->current()->first();

        if ($term === null) {
            return [];
        }

        return CourseSection::query()
            ->where('term_id', $term->id)
            ->with('course')
            ->orderBy('course_id')
            ->get()
            ->mapWithKeys(fn (CourseSection $section): array => [
                $section->id => sprintf(
                    '%s — %s [%s · %d seats left]',
                    $section->fullCode(),
                    $section->course->title,
                    $section->status === 'closed' ? 'closed' : ($section->remainingSeats() < 1 ? 'full' : 'open'),
                    $section->remainingSeats(),
                ),
            ])
            ->all();
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('id')
            ->columns([
                TextColumn::make('section.course.code')
                    ->label('Course')
                    ->searchable()
                    ->sortable()
                    ->weight('semibold'),

                TextColumn::make('section.section_number')
                    ->label('Section №')
                    ->badge()
                    ->color('gray'),

                TextColumn::make('section.term.name')
                    ->label('Term')
                    ->searchable()
                    ->toggleable(),

                TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => ucfirst($state))
                    ->color(fn (string $state): string => match ($state) {
                        'active' => 'success',
                        'completed' => 'info',
                        'dropped' => 'danger',
                        default => 'gray',
                    }),

                TextColumn::make('enrolled_at')
                    ->label('Enrolled')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(),

                TextColumn::make('dropped_at')
                    ->label('Dropped')
                    ->dateTime()
                    ->placeholder('—'),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options([
                        'active' => 'Active',
                        'completed' => 'Completed',
                        'dropped' => 'Dropped',
                    ]),

                SelectFilter::make('term')
                    ->relationship('section.term', 'name')
                    ->preload(),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])
            ->headerActions([
                CreateAction::make()
                    ->label('Register in section')
                    ->createAnother(false)
                    ->using(function (array $data, self $livewire): Enrollment {
                        /** @var Student $student */
                        $student = $livewire->getOwnerRecord();

                        $section = CourseSection::query()->findOrFail($data['section_id']);

                        if (! $section instanceof CourseSection) {
                            Notification::make()
                                ->title('Enrollment rejected')
                                ->body('Please select a valid section.')
                                ->danger()
                                ->send();

                            throw new Halt;
                        }

                        try {
                            $enrollment = app(EnrollInSection::class)->handle($student, $section);
                        } catch (ValidationException $exception) {
                            Notification::make()
                                ->title('Enrollment rejected')
                                ->body(implode(' ', collect($exception->errors())->flatten()->all()))
                                ->danger()
                                ->send();

                            throw new Halt;
                        }

                        return $enrollment;
                    }),
            ]);
    }
}
