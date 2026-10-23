<?php

namespace App\Policies;

use App\Models\Department;
use Illuminate\Database\Eloquent\Model;

class DepartmentPolicy extends AcademicResourcePolicy
{
    /**
     * Get the department id a record belongs to.
     */
    protected function departmentIdOf(Model $record): ?int
    {
        return $record instanceof Department ? $record->id : null;
    }
}
