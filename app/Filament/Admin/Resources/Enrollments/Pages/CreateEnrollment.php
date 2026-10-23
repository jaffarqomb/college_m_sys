<?php

namespace App\Filament\Admin\Resources\Enrollments\Pages;

use App\Actions\Academic\EnrollInSection;
use App\Filament\Admin\Resources\Enrollments\EnrollmentResource;
use App\Models\CourseSection;
use App\Models\Enrollment;
use App\Models\Student;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Filament\Support\Exceptions\Halt;
use Illuminate\Validation\ValidationException;

class CreateEnrollment extends CreateRecord
{
    protected static string $resource = EnrollmentResource::class;

    protected function handleRecordCreation(array $data): Enrollment
    {
        $student = Student::query()->findOrFail($data['student_id']);
        $section = CourseSection::query()->findOrFail($data['section_id']);

        if (! $student instanceof Student || ! $section instanceof CourseSection) {
            Notification::make()
                ->title('Enrollment rejected')
                ->body('Please select a valid student and section.')
                ->danger()
                ->send();

            throw new Halt;
        }

        try {
            return app(EnrollInSection::class)->handle($student, $section);
        } catch (ValidationException $exception) {
            Notification::make()
                ->title('Enrollment rejected')
                ->body(implode(' ', collect($exception->errors())->flatten()->all()))
                ->danger()
                ->send();

            throw new Halt;
        }
    }
}
