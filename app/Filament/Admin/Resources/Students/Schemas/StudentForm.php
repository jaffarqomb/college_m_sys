<?php

namespace App\Filament\Admin\Resources\Students\Schemas;

use App\Enums\UserRole;
use App\Models\Program;
use App\Models\User;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Schema;
use Filament\Support\Enums\Operation;

class StudentForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('user_id')
                    ->label('User account')
                    ->options(
                        fn (): array => User::query()
                            ->where('role', UserRole::Student->value)
                            ->whereDoesntHave('studentProfile')
                            ->orderBy('name')
                            ->pluck('name', 'id')
                            ->all()
                    )
                    ->helperText('Only student accounts without an existing student record are listed.')
                    ->visibleOn(Operation::Create)
                    ->searchable()
                    ->required(),

                Grid::make(2)
                    ->schema([
                        Select::make('program_id')
                            ->label('Program')
                            ->options(
                                fn (): array => Program::query()
                                    ->orderBy('name')
                                    ->pluck('name', 'id')
                                    ->all()
                            )
                            ->searchable()
                            ->nullable(),

                        TextInput::make('student_number')
                            ->label('Student number')
                            ->unique(ignoreRecord: true)
                            ->required()
                            ->maxLength(30),
                    ]),

                Grid::make(2)
                    ->schema([
                        DatePicker::make('admission_date')
                            ->label('Admission date')
                            ->default(now())
                            ->required(),

                        Select::make('status')
                            ->label('Enrollment status')
                            ->options([
                                'active' => 'Active',
                                'graduated' => 'Graduated',
                                'suspended' => 'Suspended',
                                'withdrawn' => 'Withdrawn',
                            ])
                            ->default('active')
                            ->required(),
                    ]),
            ]);
    }
}
