<?php

namespace App\Filament\Admin\Resources\FeeStructures\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

class FeeStructuresTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('term.name')
                    ->label('Term')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('name')
                    ->label('Fee')
                    ->searchable()
                    ->weight('semibold'),

                TextColumn::make('program.name')
                    ->label('Program')
                    ->placeholder('All programs')
                    ->searchable(),

                TextColumn::make('amount')
                    ->label('Amount')
                    ->numeric()
                    ->sortable(),

                IconColumn::make('is_active')
                    ->label('Active')
                    ->boolean(),
            ])
            ->filters([
                SelectFilter::make('term')
                    ->relationship('term', 'name')
                    ->preload(),

                TernaryFilter::make('is_active'),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
