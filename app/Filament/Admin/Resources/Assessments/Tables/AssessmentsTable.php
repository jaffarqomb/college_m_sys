<?php

namespace App\Filament\Admin\Resources\Assessments\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class AssessmentsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('section')
                    ->label('Section')
                    ->state(fn ($record): string => $record->section->fullCode())
                    ->sortable(),

                TextColumn::make('section.course.title')
                    ->label('Course')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('title')
                    ->label('Assessment')
                    ->searchable()
                    ->sortable()
                    ->weight('semibold'),

                TextColumn::make('type')
                    ->label('Type')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'assignment' => 'Assignment',
                        'midterm' => 'Midterm',
                        'final' => 'Final exam',
                        'lab' => 'Lab work',
                        default => 'Quiz',
                    })
                    ->color(fn (string $state): string => match ($state) {
                        'midterm', 'final' => 'warning',
                        'lab' => 'success',
                        default => 'primary',
                    }),

                TextColumn::make('weight')
                    ->label('Weight %')
                    ->numeric()
                    ->sortable(),

                TextColumn::make('max_score')
                    ->label('Max score')
                    ->numeric()
                    ->sortable(),

                TextColumn::make('grades_count')
                    ->counts('grades')
                    ->label('Scored')
                    ->numeric()
                    ->sortable(),

                TextColumn::make('conducted_at')
                    ->date()
                    ->label('Conducted')
                    ->sortable()
                    ->toggleable(),

                TextColumn::make('due_date')
                    ->date()
                    ->label('Due')
                    ->sortable()
                    ->toggleable(),
            ])
            ->filters([
                SelectFilter::make('type')
                    ->options([
                        'quiz' => 'Quiz',
                        'assignment' => 'Assignment',
                        'midterm' => 'Midterm',
                        'final' => 'Final exam',
                        'lab' => 'Lab work',
                    ]),

                SelectFilter::make('course')
                    ->relationship('section.course', 'code')
                    ->preload()
                    ->searchable(),
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
