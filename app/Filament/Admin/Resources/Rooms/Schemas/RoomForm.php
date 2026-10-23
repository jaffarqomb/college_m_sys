<?php

namespace App\Filament\Admin\Resources\Rooms\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class RoomForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('code')
                    ->label('Room code')
                    ->helperText('Unique identifier shown on timetables, e.g. "A-101"')
                    ->required()
                    ->maxLength(30)
                    ->unique(ignoreRecord: true),

                TextInput::make('building')
                    ->label('Building')
                    ->maxLength(255),

                TextInput::make('capacity')
                    ->label('Seat capacity')
                    ->numeric()
                    ->minValue(1)
                    ->maxValue(2000)
                    ->default(30)
                    ->required(),

                Select::make('type')
                    ->label('Room type')
                    ->options([
                        'lecture' => 'Lecture room',
                        'lab' => 'Laboratory',
                        'auditorium' => 'Auditorium',
                        'seminar' => 'Seminar room',
                    ])
                    ->default('lecture')
                    ->required(),
            ]);
    }
}
