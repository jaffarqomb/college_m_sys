<?php

namespace App\Filament\Admin\Resources\Departments\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class DepartmentsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label('Department')
                    ->searchable()
                    ->sortable()
                    ->weight('semibold'),

                TextColumn::make('code')
                    ->label('Code')
                    ->badge()
                    ->color('primary')
                    ->searchable(),

                TextColumn::make('programs_count')
                    ->counts('programs')
                    ->label('Programs')
                    ->numeric()
                    ->sortable(),

                TextColumn::make('courses_count')
                    ->counts('courses')
                    ->label('Courses')
                    ->numeric()
                    ->sortable(),

                TextColumn::make('instructors_count')
                    ->counts('instructors')
                    ->label('Staff')
                    ->numeric()
                    ->sortable(),

                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                //
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
