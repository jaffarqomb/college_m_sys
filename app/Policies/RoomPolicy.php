<?php

namespace App\Policies;

use Illuminate\Database\Eloquent\Model;

class RoomPolicy extends AcademicResourcePolicy
{
    /**
     * Rooms are campus-wide facilities, not tied to a department.
     */
    protected function departmentIdOf(Model $record): ?int
    {
        return null;
    }
}
