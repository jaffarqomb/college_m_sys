<?php

namespace App\Filament\Admin\Resources\Departments\Schemas;

use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class DepartmentForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->label('Department name')
                    ->required()
                    ->maxLength(255)
                    ->unique(ignoreRecord: true),

                TextInput::make('code')
                    ->label('Department code')
                    ->helperText('Short code used in course codes, e.g. "CS" for Computer Science')
                    ->required()
                    ->maxLength(20)
                    ->unique(ignoreRecord: true)
                    ->afterStateUpdated(fn ($state, TextInput $component) => strtoupper($state)),

                Textarea::make('description')
                    ->label('Description')
                    ->columnSpanFull()
                    ->maxLength(2000),
            ]);
    }
}
