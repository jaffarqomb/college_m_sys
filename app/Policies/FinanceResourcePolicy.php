<?php

namespace App\Policies;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/**
 * Base authorization for finance-office resources.
 *
 * Access rules:
 *  - System admins, registrars and finance officers may browse records.
 *  - Department heads have no finance panel access.
 *  - System admins and registrars manage records; finance officers are
 *    read-only on structure and must use the payment-recording actions.
 */
abstract class FinanceResourcePolicy
{
    /**
     * Determine whether the user can browse the resource at all.
     */
    public function viewAny(User $user): bool
    {
        return $user->isSystemAdmin()
            || $user->isRegistrar()
            || $user->isFinanceOfficer();
    }

    /**
     * Determine whether the user can view a single record.
     */
    public function view(User $user, Model $record): bool
    {
        return $this->viewAny($user);
    }

    /**
     * Determine whether the user can create records.
     */
    public function create(User $user): bool
    {
        return $user->isSystemAdmin() || $user->isRegistrar();
    }

    /**
     * Determine whether the user can update a record.
     */
    public function update(User $user, Model $record): bool
    {
        return $user->isSystemAdmin() || $user->isRegistrar();
    }

    /**
     * Determine whether the user can delete a record.
     */
    public function delete(User $user, Model $record): bool
    {
        return $user->isSystemAdmin() || $user->isRegistrar();
    }

    /**
     * Determine whether the user can bulk delete records.
     */
    public function deleteAny(User $user): bool
    {
        return $user->isSystemAdmin() || $user->isRegistrar();
    }

    /**
     * Determine whether the user can restore a soft-deleted record.
     */
    public function restore(User $user, Model $record): bool
    {
        return $user->isSystemAdmin();
    }

    /**
     * Determine whether the user can bulk restore records.
     */
    public function restoreAny(User $user): bool
    {
        return $user->isSystemAdmin();
    }

    /**
     * Determine whether the user can permanently delete records.
     */
    public function forceDelete(User $user, Model $record): bool
    {
        return $user->isSystemAdmin();
    }

    /**
     * Determine whether the user can bulk force-delete records.
     */
    public function forceDeleteAny(User $user): bool
    {
        return $user->isSystemAdmin();
    }
}
