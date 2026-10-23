<?php

namespace App\Filament\Admin\Resources\Assessments\Schemas;

use App\Models\CourseSection;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class AssessmentForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('section_id')
                    ->label('Section')
                    ->relationship(
                        'section',
                        'section_number',
                        fn ($query): mixed => $query
                            ->with('course')
                            ->whereHas('course', fn ($course): mixed => $course->where('is_active', true)),
                    )
                    ->getOptionLabelFromRecordUsing(fn (CourseSection $record): string => $record->fullCode().' — '.$record->course->title)
                    ->preload()
                    ->searchable()
                    ->required(),

                TextInput::make('title')
                    ->label('Assessment title')
                    ->helperText('e.g. "Quiz 1", "Midterm Exam" or "Final Project"')
                    ->required()
                    ->maxLength(255),

                Select::make('type')
                    ->label('Type')
                    ->options([
                        'quiz' => 'Quiz',
                        'assignment' => 'Assignment',
                        'midterm' => 'Midterm',
                        'final' => 'Final exam',
                        'lab' => 'Lab work',
                    ])
                    ->default('quiz')
                    ->required(),

                TextInput::make('weight')
                    ->label('Weight (%)')
                    ->helperText('Share of the final grade; weights should sum to 100 across all assessments')
                    ->numeric()
                    ->minValue(0)
                    ->maxValue(100)
                    ->default(10)
                    ->required(),

                TextInput::make('max_score')
                    ->label('Maximum score')
                    ->numeric()
                    ->minValue(1)
                    ->maxValue(9999)
                    ->default(100)
                    ->required(),

                DatePicker::make('conducted_at')
                    ->label('Conducted on')
                    ->nullable(),

                DatePicker::make('due_date')
                    ->label('Due on')
                    ->nullable()
                    ->afterOrEqual('conducted_at'),
            ]);
    }
}
