<?php

namespace App\Filament\Admin\Resources\Courses\RelationManagers;

use Filament\Actions\AttachAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DetachAction;
use Filament\Actions\DetachBulkAction;
use Filament\Forms\Components\Select;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class PrerequisitesRelationManager extends RelationManager
{
    protected static string $relationship = 'prerequisites';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('prerequisite_course_id')
                    ->label('Course')
                    ->relationship('prerequisiteCourse', 'title')
                    ->required()
                    ->searchable(),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('title')
            ->columns([
                TextColumn::make('code')
                    ->label('Code')
                    ->badge()
                    ->color('primary'),

                TextColumn::make('title')
                    ->label('Course')
                    ->searchable()
                    ->weight('semibold'),

                TextColumn::make('department.name')
                    ->label('Department')
                    ->searchable(),

                TextColumn::make('credits')
                    ->label('Credits')
                    ->numeric(),
            ])
            ->filters([
                //
            ])
            ->headerActions([
                AttachAction::make()
                    ->recordSelectOptionsQuery(
                        fn ($query) => $query
                            ->whereKeyNot($this->getOwnerRecord()->getKey())
                            ->orderBy('code')
                    ),
            ])
            ->recordActions([
                DetachAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DetachBulkAction::make(),
                ]),
            ]);
    }
}
