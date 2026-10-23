<?php

namespace App\Policies;

use App\Models\SectionSchedule;
use Illuminate\Database\Eloquent\Model;

class SectionSchedulePolicy extends AcademicResourcePolicy
{
    /**
     * Get the department id a record belongs to.
     */
    protected function departmentIdOf(Model $record): ?int
    {
        return $record instanceof SectionSchedule
            ? $record->section->course->department_id
            : null;
    }
}
