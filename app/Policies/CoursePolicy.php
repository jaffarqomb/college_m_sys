<?php

namespace App\Policies;

use App\Models\Course;
use Illuminate\Database\Eloquent\Model;

class CoursePolicy extends AcademicResourcePolicy
{
    /**
     * Get the department id a record belongs to.
     */
    protected function departmentIdOf(Model $record): ?int
    {
        return $record instanceof Course ? $record->department_id : null;
    }
}
