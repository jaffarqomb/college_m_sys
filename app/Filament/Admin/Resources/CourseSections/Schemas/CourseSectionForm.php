<?php

namespace App\Filament\Admin\Resources\CourseSections\Schemas;

use App\Models\Instructor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Builder;

class CourseSectionForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Grid::make(2)
                    ->schema([
                        Select::make('course_id')
                            ->label('Course')
                            ->relationship(
                                'course',
                                'title',
                                modifyQueryUsing: fn (Builder $query): Builder => $query->where('is_active', true)->orderBy('code')
                            )
                            ->searchable()
                            ->preload()
                            ->required(),

                        Select::make('term_id')
                            ->label('Academic term')
                            ->relationship('term', 'name')
                            ->preload()
                            ->searchable()
                            ->required(),
                    ]),

                Grid::make(2)
                    ->schema([
                        Select::make('instructor_id')
                            ->label('Instructor')
                            ->options(
                                fn (): array => Instructor::query()
                                    ->with('user')
                                    ->orderBy('employee_number')
                                    ->get()
                                    ->mapWithKeys(fn ($instructor): array => [
                                        $instructor->id => $instructor->displayName().' ('.$instructor->employee_number.')',
                                    ])
                                    ->all()
                            )
                            ->searchable()
                            ->nullable(),

                        TextInput::make('section_number')
                            ->label('Section number')
                            ->placeholder('01')
                            ->default('01')
                            ->required()
                            ->maxLength(10),
                    ]),

                Grid::make(2)
                    ->schema([
                        TextInput::make('capacity')
                            ->label('Seat capacity')
                            ->numeric()
                            ->minValue(1)
                            ->maxValue(500)
                            ->default(30)
                            ->required(),

                        Toggle::make('requires_lab')
                            ->label('Requires laboratory room')
                            ->default(false),
                    ]),

                Select::make('status')
                    ->label('Enrollment status')
                    ->options([
                        'open' => 'Open',
                        'closed' => 'Closed',
                        'cancelled' => 'Cancelled',
                    ])
                    ->default('open')
                    ->required(),
            ]);
    }
}
