<?php

namespace App\Filament\Admin\Resources\Programs\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class ProgramForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('department_id')
                    ->label('Department')
                    ->relationship('department', 'name')
                    ->preload()
                    ->searchable()
                    ->required(),

                TextInput::make('name')
                    ->label('Program name')
                    ->required()
                    ->maxLength(255)
                    ->unique(ignoreRecord: true),

                TextInput::make('code')
                    ->label('Program code')
                    ->helperText('Unique code students reference, e.g. "BS-CS"')
                    ->required()
                    ->maxLength(20)
                    ->unique(ignoreRecord: true)
                    ->afterStateUpdated(fn ($state) => mb_strtoupper((string) $state)),

                Select::make('level')
                    ->label('Degree level')
                    ->options([
                        'associate' => 'Associate',
                        'bachelor' => 'Bachelor',
                        'master' => 'Master',
                        'doctorate' => 'Doctorate',
                    ])
                    ->default('bachelor')
                    ->live()
                    ->required(),

                TextInput::make('duration_years')
                    ->label('Duration (years)')
                    ->numeric()
                    ->minValue(1)
                    ->maxValue(8)
                    ->default(4)
                    ->required(),

                Textarea::make('description')
                    ->label('Description')
                    ->columnSpanFull()
                    ->maxLength(5000),
            ]);
    }
}
