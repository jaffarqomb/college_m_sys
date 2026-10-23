<?php

namespace App\Filament\Admin\Resources\Enrollments\Schemas;

use App\Models\AcademicTerm;
use App\Models\CourseSection;
use App\Models\Student;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Enums\Operation;
use Illuminate\Validation\Rules\Unique;

class EnrollmentForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('student_id')
                    ->label('Student')
                    ->options(fn (): array => Student::query()
                        ->active()
                        ->with('user')
                        ->orderBy('student_number')
                        ->get()
                        ->mapWithKeys(fn (Student $student): array => [
                            $student->id => sprintf('%s (%s)', $student->displayName(), $student->student_number),
                        ])
                        ->all())
                    ->searchable()
                    ->required()
                    ->visibleOn(Operation::Create)
                    ->disabledOn(Operation::Edit),

                Select::make('section_id')
                    ->label('Section')
                    ->options(function (): array {
                        $term = AcademicTerm::query()->current()->first();

                        if ($term === null) {
                            return [];
                        }

                        return CourseSection::query()
                            ->where('term_id', $term->id)
                            ->with('course')
                            ->orderBy('course_id')
                            ->get()
                            ->mapWithKeys(fn (CourseSection $section): array => [
                                $section->id => sprintf(
                                    '%s — %s [%s · %d seats left]',
                                    $section->fullCode(),
                                    $section->course->title,
                                    $section->status === 'closed' ? 'closed' : ($section->remainingSeats() < 1 ? 'full' : 'open'),
                                    $section->remainingSeats(),
                                ),
                            ])
                            ->all();
                    })
                    ->searchable()
                    ->required()
                    ->visibleOn(Operation::Create)
                    ->disabledOn(Operation::Edit)
                    ->helperText('Registration enforces capacity, prerequisites, deadlines and schedule conflicts.')
                    ->unique(modifyRuleUsing: function (Unique $rule, Get $get): Unique {
                        return $rule->where('student_id', (int) $get('student_id'));
                    }),

                Select::make('status')
                    ->label('Status')
                    ->options([
                        'active' => 'Active',
                        'completed' => 'Completed',
                        'dropped' => 'Dropped',
                    ])
                    ->default('active')
                    ->required()
                    ->visibleOn(Operation::Edit),

                DatePicker::make('dropped_at')
                    ->label('Dropped at')
                    ->visibleOn(Operation::Edit),
            ]);
    }
}
