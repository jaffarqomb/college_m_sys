<?php

namespace App\Filament\Admin\Resources\Instructors\Schemas;

use App\Enums\UserRole;
use App\Models\Department;
use App\Models\User;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Schema;
use Filament\Support\Enums\Operation;

class InstructorForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('user_id')
                    ->label('User account')
                    ->options(
                        fn (): array => User::query()
                            ->where('role', UserRole::Instructor->value)
                            ->whereDoesntHave('instructorProfile')
                            ->orderBy('name')
                            ->pluck('name', 'id')
                            ->all()
                    )
                    ->helperText('Only instructor accounts without an existing instructor record are listed.')
                    ->visibleOn(Operation::Create)
                    ->searchable()
                    ->required(),

                Grid::make(2)
                    ->schema([
                        Select::make('department_id')
                            ->label('Department')
                            ->options(
                                fn (): array => Department::query()
                                    ->orderBy('name')
                                    ->pluck('name', 'id')
                                    ->all()
                            )
                            ->searchable()
                            ->required(),

                        TextInput::make('employee_number')
                            ->label('Employee number')
                            ->unique(ignoreRecord: true)
                            ->required()
                            ->maxLength(30),
                    ]),

                Grid::make(2)
                    ->schema([
                        TextInput::make('title')
                            ->label('Title')
                            ->placeholder('Prof. / Dr. / Ms.')
                            ->maxLength(40),

                        TextInput::make('office')
                            ->label('Office')
                            ->placeholder('A-101')
                            ->maxLength(40),
                    ]),

                Grid::make(2)
                    ->schema([
                        TextInput::make('office_hours')
                            ->label('Office hours')
                            ->placeholder('Sun - Thu, 10:00 - 12:00')
                            ->maxLength(255),

                        Toggle::make('is_head')
                            ->label('Department head'),
                    ]),
            ]);
    }
}
