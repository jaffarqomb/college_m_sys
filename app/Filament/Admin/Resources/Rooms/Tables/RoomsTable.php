<?php

namespace App\Filament\Admin\Resources\Rooms\Tables;

use App\Models\Room;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class RoomsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('code')
                    ->label('Room')
                    ->badge()
                    ->color('primary')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('building')
                    ->label('Building')
                    ->searchable()
                    ->sortable()
                    ->placeholder('—'),

                TextColumn::make('capacity')
                    ->label('Capacity')
                    ->numeric()
                    ->sortable(),

                TextColumn::make('type')
                    ->label('Type')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => ucfirst($state))
                    ->color(fn (string $state): string => match ($state) {
                        'lab' => 'info',
                        'auditorium' => 'warning',
                        'seminar' => 'gray',
                        default => 'success',
                    }),

                TextColumn::make('section_schedules_count')
                    ->counts('sectionSchedules')
                    ->label('Weekly slots')
                    ->numeric()
                    ->sortable(),

                TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('type')
                    ->options([
                        'lecture' => 'Lecture room',
                        'lab' => 'Laboratory',
                        'auditorium' => 'Auditorium',
                        'seminar' => 'Seminar room',
                    ]),

                SelectFilter::make('building')
                    ->options(fn (): array => Room::query()
                        ->distinct()
                        ->orderBy('building')
                        ->whereNotNull('building')
                        ->pluck('building', 'building')
                        ->all()),
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
