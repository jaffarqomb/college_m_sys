<?php

namespace App\Policies;

use App\Models\Instructor;
use Illuminate\Database\Eloquent\Model;

class InstructorPolicy extends AcademicResourcePolicy
{
    /**
     * Get the department id a record belongs to.
     */
    protected function departmentIdOf(Model $record): ?int
    {
        return $record instanceof Instructor ? $record->department_id : null;
    }
}
