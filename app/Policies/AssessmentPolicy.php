<?php

namespace App\Policies;

use App\Models\Assessment;
use Illuminate\Database\Eloquent\Model;

class AssessmentPolicy extends AcademicResourcePolicy
{
    /**
     * Get the department id a record belongs to.
     */
    protected function departmentIdOf(Model $record): ?int
    {
        return $record instanceof Assessment ? $record->section->course->department_id : null;
    }
}
