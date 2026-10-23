<?php

namespace App\Filament\Admin\Resources\FeeStructures\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class FeeStructureForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make()
                    ->columns(2)
                    ->components([
                        Select::make('term_id')
                            ->label('Term')
                            ->relationship('term', 'name')
                            ->preload()
                            ->required(),

                        Select::make('program_id')
                            ->label('Program')
                            ->relationship('program', 'name')
                            ->helperText('Leave empty to bill every program\'s students.')
                            ->preload(),

                        TextInput::make('name')
                            ->label('Fee name')
                            ->required()
                            ->maxLength(255),

                        TextInput::make('amount')
                            ->label('Amount')
                            ->numeric()
                            ->minValue(0)
                            ->required(),

                        Toggle::make('is_active')
                            ->label('Active')
                            ->helperText('Inactive structures are ignored by new billing runs.')
                            ->default(true),
                    ]),
            ]);
    }
}
