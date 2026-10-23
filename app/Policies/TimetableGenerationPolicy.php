<?php

namespace App\Policies;

use App\Models\TimetableGeneration;
use App\Models\User;

/**
 * Authorization for AI timetable drafts.
 *
 * All panel staff may review drafts, but only system admins and registrars
 * may generate, adjust or publish them.
 */
class TimetableGenerationPolicy
{
    /**
     * Determine whether the user can browse drafts at all.
     */
    public function viewAny(User $user): bool
    {
        return $user->role->canAccessPanel();
    }

    /**
     * Determine whether the user can view a draft.
     */
    public function view(User $user, TimetableGeneration $timetableGeneration): bool
    {
        return $user->role->canAccessPanel();
    }

    /**
     * Determine whether the user can generate new drafts.
     */
    public function create(User $user): bool
    {
        return $user->isSystemAdmin() || $user->isRegistrar();
    }

    /**
     * Determine whether the user can adjust any draft.
     */
    public function updateAny(User $user): bool
    {
        return $user->isSystemAdmin() || $user->isRegistrar();
    }

    /**
     * Determine whether the user can adjust the given draft.
     */
    public function update(User $user, TimetableGeneration $timetableGeneration): bool
    {
        return $user->isSystemAdmin() || $user->isRegistrar();
    }

    /**
     * Determine whether the user can publish any draft.
     */
    public function publishAny(User $user): bool
    {
        return $user->isSystemAdmin() || $user->isRegistrar();
    }

    /**
     * Determine whether the user can delete the given draft.
     */
    public function delete(User $user, TimetableGeneration $timetableGeneration): bool
    {
        return $user->isSystemAdmin() || $user->isRegistrar();
    }

    /**
     * Determine whether the user can delete any draft.
     */
    public function deleteAny(User $user): bool
    {
        return $user->isSystemAdmin() || $user->isRegistrar();
    }
}
