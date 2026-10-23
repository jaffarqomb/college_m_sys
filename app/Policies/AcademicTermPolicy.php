<?php

namespace App\Policies;

use Illuminate\Database\Eloquent\Model;

class AcademicTermPolicy extends AcademicResourcePolicy
{
    /**
     * Terms are institution-wide, not tied to a department.
     */
    protected function departmentIdOf(Model $record): ?int
    {
        return null;
    }
}
