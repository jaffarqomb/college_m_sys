<?php

namespace App\Filament\Admin\Resources\Students\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class StudentsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('student_number')
                    ->label('Student №')
                    ->badge()
                    ->color('primary')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('user.name')
                    ->label('Name')
                    ->searchable()
                    ->sortable()
                    ->weight('semibold'),

                TextColumn::make('user.email')
                    ->label('Email')
                    ->searchable()
                    ->toggleable(),

                TextColumn::make('program.name')
                    ->label('Program')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('program.department.name')
                    ->label('Department')
                    ->searchable()
                    ->toggleable(),

                TextColumn::make('admission_date')
                    ->label('Admitted')
                    ->date()
                    ->sortable(),

                TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => ucfirst($state))
                    ->color(fn (string $state): string => match ($state) {
                        'active' => 'success',
                        'graduated' => 'info',
                        'suspended' => 'warning',
                        default => 'gray',
                    }),

                TextColumn::make('enrollments_count')
                    ->counts('enrollments')
                    ->label('Enrollments')
                    ->numeric()
                    ->sortable(),

                TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options([
                        'active' => 'Active',
                        'graduated' => 'Graduated',
                        'suspended' => 'Suspended',
                        'withdrawn' => 'Withdrawn',
                    ]),

                SelectFilter::make('program')
                    ->relationship('program', 'name')
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
