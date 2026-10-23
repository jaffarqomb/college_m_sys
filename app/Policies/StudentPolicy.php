<?php

namespace App\Policies;

use App\Models\Student;
use Illuminate\Database\Eloquent\Model;

class StudentPolicy extends AcademicResourcePolicy
{
    /**
     * Get the department id a record belongs to.
     */
    protected function departmentIdOf(Model $record): ?int
    {
        return $record instanceof Student ? $record->program?->department_id : null;
    }
}
