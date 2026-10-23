<?php

namespace App\Policies;

use App\Models\Program;
use Illuminate\Database\Eloquent\Model;

class ProgramPolicy extends AcademicResourcePolicy
{
    /**
     * Get the department id a record belongs to.
     */
    protected function departmentIdOf(Model $record): ?int
    {
        return $record instanceof Program ? $record->department_id : null;
    }
}
