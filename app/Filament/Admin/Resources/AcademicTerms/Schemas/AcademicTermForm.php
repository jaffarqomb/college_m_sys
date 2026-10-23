<?php

namespace App\Filament\Admin\Resources\AcademicTerms\Schemas;

use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Schema;

class AcademicTermForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->label('Term name')
                    ->helperText('Shown everywhere, e.g. "Fall 2026"')
                    ->required()
                    ->maxLength(255),

                TextInput::make('code')
                    ->label('Term code')
                    ->helperText('Short unique code, e.g. "F2026-1"')
                    ->required()
                    ->maxLength(30)
                    ->unique(ignoreRecord: true),

                Grid::make(2)
                    ->schema([
                        DatePicker::make('start_date')
                            ->label('Start date')
                            ->required(),

                        DatePicker::make('end_date')
                            ->label('End date')
                            ->minDate(fn (callable $get) => $get('start_date'))
                            ->required(),
                    ]),

                DatePicker::make('registration_deadline')
                    ->label('Registration deadline')
                    ->helperText('Leave empty for no deadline.'),

                Toggle::make('is_current')
                    ->label('Current term')
                    ->helperText('Marks the active term across the system. Enabling this automatically unsets it from other terms.')
                    ->default(false),
            ]);
    }
}
