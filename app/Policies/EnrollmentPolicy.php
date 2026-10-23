<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\Enrollment;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class EnrollmentPolicy extends AcademicResourcePolicy
{
    /**
     * Get the department id a record belongs to (via the student's program).
     */
    protected function departmentIdOf(Model $record): ?int
    {
        $student = $record instanceof Enrollment ? $record->student : null;

        return $student?->program?->department_id;
    }

    /**
     * Only system admins and registrars may register students into sections.
     */
    public function create(User $user): bool
    {
        return $user->hasRole(UserRole::SystemAdmin, UserRole::Registrar);
    }
}
