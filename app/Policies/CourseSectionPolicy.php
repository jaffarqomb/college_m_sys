<?php

namespace App\Policies;

use App\Models\CourseSection;
use Illuminate\Database\Eloquent\Model;

class CourseSectionPolicy extends AcademicResourcePolicy
{
    /**
     * Get the department id a record belongs to.
     */
    protected function departmentIdOf(Model $record): ?int
    {
        return $record instanceof CourseSection ? $record->course->department_id : null;
    }
}
