<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/**
 * Base authorization for academic staff on domain resources.
 *
 * Access rules:
 *  - All panel staff (system admin, registrar, department head, finance
 *    officer) may browse records.
 *  - System admins and registrars may manage everything.
 *  - Department heads may manage records inside their own department only.
 *  - Finance officers are read-only viewers.
 */
abstract class AcademicResourcePolicy
{
    /**
     * Determine whether the user can browse the resource at all.
     */
    public function viewAny(User $user): bool
    {
        return $user->role->canAccessPanel();
    }

    /**
     * Determine whether the user can view a single record.
     */
    public function view(User $user, Model $record): bool
    {
        return $user->role->canAccessPanel();
    }

    /**
     * Determine whether the user can create records.
     */
    public function create(User $user): bool
    {
        return $user->hasRole(UserRole::SystemAdmin, UserRole::Registrar);
    }

    /**
     * Determine whether the user can update a record.
     */
    public function update(User $user, Model $record): bool
    {
        return $this->canManageRecord($user, $record);
    }

    /**
     * Determine whether the user can delete a record.
     */
    public function delete(User $user, Model $record): bool
    {
        return $this->canManageRecord($user, $record);
    }

    /**
     * Determine whether the user can bulk delete records.
     */
    public function deleteAny(User $user): bool
    {
        return $user->hasRole(UserRole::SystemAdmin, UserRole::Registrar);
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
     * Determine whether the user can permanently delete a record.
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

    /**
     * Determine whether the user can reorder records in the table.
     */
    public function reorder(User $user): bool
    {
        return $user->hasRole(UserRole::SystemAdmin, UserRole::Registrar);
    }

    /**
     * Determine whether the user can attach any record to a relation.
     */
    public function attachAny(User $user): bool
    {
        return $user->hasRole(UserRole::SystemAdmin, UserRole::Registrar);
    }

    /**
     * Determine whether the user can attach a record to a relation.
     */
    public function attach(User $user): bool
    {
        return $user->hasRole(UserRole::SystemAdmin, UserRole::Registrar);
    }

    /**
     * Determine whether the user can detach a record from a relation.
     */
    public function detach(User $user, Model $record): bool
    {
        return $this->canManageRecord($user, $record);
    }

    /**
     * Determine whether the user can bulk detach records from a relation.
     */
    public function detachAny(User $user): bool
    {
        return $user->hasRole(UserRole::SystemAdmin, UserRole::Registrar);
    }

    /**
     * Get the department id a record belongs to.
     */
    abstract protected function departmentIdOf(Model $record): ?int;

    /**
     * Determine whether the user can manage the given record.
     */
    protected function canManageRecord(User $user, Model $record): bool
    {
        if ($user->isSystemAdmin() || $user->isRegistrar()) {
            return true;
        }

        if ($user->isDepartmentHead()) {
            return $this->departmentIdOf($record) === $user->managedDepartmentId();
        }

        return false;
    }
}
