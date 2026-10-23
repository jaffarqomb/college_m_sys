<?php

namespace App\Filament\Admin\Resources\Courses\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class CourseForm
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

                TextInput::make('code')
                    ->label('Course code')
                    ->helperText('Unique code, e.g. "CS101" or "MA204"')
                    ->required()
                    ->maxLength(20)
                    ->unique(ignoreRecord: true)
                    ->afterStateUpdated(fn ($state) => mb_strtoupper((string) $state)),

                TextInput::make('title')
                    ->label('Course title')
                    ->required()
                    ->maxLength(255),

                TextInput::make('credits')
                    ->label('Credit hours')
                    ->numeric()
                    ->minValue(1)
                    ->maxValue(12)
                    ->default(3)
                    ->required(),

                TextInput::make('year_level')
                    ->label('Year level')
                    ->numeric()
                    ->minValue(1)
                    ->maxValue(8)
                    ->default(1)
                    ->required(),

                Toggle::make('requires_lab')
                    ->label('Requires laboratory room')
                    ->default(false),

                Toggle::make('is_active')
                    ->label('Active in catalog')
                    ->helperText('Inactive courses cannot be scheduled or enrolled into.')
                    ->default(true),

                Textarea::make('description')
                    ->label('Description')
                    ->columnSpanFull()
                    ->maxLength(5000),
            ]);
    }
}
