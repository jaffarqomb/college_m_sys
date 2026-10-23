<?php

namespace App\Filament\Admin\Resources\AcademicTerms\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

class AcademicTermsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('code')
                    ->label('Code')
                    ->badge()
                    ->color('primary')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('name')
                    ->label('Term')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('start_date')
                    ->label('Starts')
                    ->date()
                    ->sortable(),

                TextColumn::make('end_date')
                    ->label('Ends')
                    ->date()
                    ->sortable(),

                TextColumn::make('registration_deadline')
                    ->label('Registration until')
                    ->date()
                    ->sortable()
                    ->placeholder('Open enrollment'),

                TextColumn::make('is_current')
                    ->label('Current')
                    ->badge()
                    ->formatStateUsing(fn ($state): string => $state === true ? 'Current term' : '')
                    ->color('success')
                    ->visible(fn ($state): bool => $state === true),

                TextColumn::make('course_sections_count')
                    ->counts('courseSections')
                    ->label('Sections')
                    ->numeric()
                    ->sortable(),

                TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                TernaryFilter::make('is_current')
                    ->label('Current term'),
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
