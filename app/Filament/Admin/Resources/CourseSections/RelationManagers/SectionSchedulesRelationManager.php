<?php

namespace App\Filament\Admin\Resources\CourseSections\RelationManagers;

use App\Models\Room;
use App\Models\SectionSchedule;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TimePicker;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class SectionSchedulesRelationManager extends RelationManager
{
    protected static string $relationship = 'sectionSchedules';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Grid::make(2)
                    ->schema([
                        Select::make('day_of_week')
                            ->label('Day')
                            ->options(SectionSchedule::DAYS)
                            ->required(),

                        Select::make('room_id')
                            ->label('Room')
                            ->options(
                                fn (): array => Room::query()
                                    ->orderBy('code')
                                    ->pluck('code', 'id')
                                    ->all()
                            )
                            ->searchable()
                            ->nullable(),
                    ]),

                Grid::make(2)
                    ->schema([
                        TimePicker::make('start_time')
                            ->label('Starts')
                            ->seconds(false)
                            ->required(),

                        TimePicker::make('end_time')
                            ->label('Ends')
                            ->seconds(false)
                            ->rule('date_format:H:i')
                            ->required(),
                    ]),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('day_of_week')
            ->reorderable('start_time')
            ->columns([
                TextColumn::make('day_of_week')
                    ->label('Day')
                    ->formatStateUsing(fn (int $state): string => SectionSchedule::DAYS[$state] ?? 'Unknown')
                    ->sortable(),

                TextColumn::make('start_time')
                    ->label('Starts')
                    ->sortable(),

                TextColumn::make('end_time')
                    ->label('Ends')
                    ->sortable(),

                TextColumn::make('room.code')
                    ->label('Room')
                    ->searchable()
                    ->placeholder('TBA'),
            ])
            ->filters([
                //
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
                CreateAction::make(),
            ]);
    }
}
