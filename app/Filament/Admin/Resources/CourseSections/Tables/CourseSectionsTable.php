<?php

namespace App\Filament\Admin\Resources\CourseSections\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class CourseSectionsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('course.code')
                    ->label('Course')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('section_number')
                    ->label('Section')
                    ->badge()
                    ->color('secondary'),

                TextColumn::make('term.name')
                    ->label('Term')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('course.title')
                    ->label('Title')
                    ->searchable()
                    ->toggleable(),

                TextColumn::make('instructor.user.name')
                    ->label('Instructor')
                    ->searchable()
                    ->placeholder('Unassigned'),

                TextColumn::make('capacity')
                    ->label('Capacity')
                    ->numeric()
                    ->sortable(),

                TextColumn::make('registered_count')
                    ->label('Registered')
                    ->state(fn ($record): int => $record->enrolledCount())
                    ->numeric(),

                TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => ucfirst($state))
                    ->color(fn (string $state): string => match ($state) {
                        'closed' => 'warning',
                        'cancelled' => 'danger',
                        default => 'success',
                    }),

                TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('term')
                    ->label('Academic term')
                    ->relationship('term', 'name')
                    ->preload(),

                SelectFilter::make('status')
                    ->options([
                        'open' => 'Open',
                        'closed' => 'Closed',
                        'cancelled' => 'Cancelled',
                    ]),

                SelectFilter::make('department')
                    ->label('Department')
                    ->relationship(
                        'course.department',
                        'name',
                    )
                    ->preload(),
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
