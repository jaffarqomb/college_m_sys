<?php

namespace App\Enums;

enum UserRole: string
{
    case Student = 'student';
    case Instructor = 'instructor';
    case Registrar = 'registrar';
    case DepartmentHead = 'department_head';
    case FinanceOfficer = 'finance_officer';
    case SystemAdmin = 'system_admin';

    /**
     * Get the display label for the role.
     */
    public function label(): string
    {
        return match ($this) {
            self::Student => 'Student',
            self::Instructor => 'Instructor',
            self::Registrar => 'Registrar',
            self::DepartmentHead => 'Department Head',
            self::FinanceOfficer => 'Finance Officer',
            self::SystemAdmin => 'System Administrator',
        };
    }

    /**
     * Roles that may access the Filament administrative panel.
     *
     * @return array<self>
     */
    public static function panelRoles(): array
    {
        return [
            self::Registrar,
            self::DepartmentHead,
            self::FinanceOfficer,
            self::SystemAdmin,
        ];
    }

    /**
     * Determine if this role can access the Filament administrative panel.
     */
    public function canAccessPanel(): bool
    {
        return in_array($this, self::panelRoles(), true);
    }

    /**
     * Determine if this role is a student.
     */
    public function isStudent(): bool
    {
        return $this === self::Student;
    }

    /**
     * Determine if this role is an instructor.
     */
    public function isInstructor(): bool
    {
        return $this === self::Instructor;
    }

    /**
     * Determine if this role is a registrar.
     */
    public function isRegistrar(): bool
    {
        return $this === self::Registrar;
    }

    /**
     * Determine if this role is the department head.
     */
    public function isDepartmentHead(): bool
    {
        return $this === self::DepartmentHead;
    }

    /**
     * Determine if this role is part of the finance office.
     */
    public function isFinanceOfficer(): bool
    {
        return $this === self::FinanceOfficer;
    }

    /**
     * Determine if this role is a system administrator.
     */
    public function isSystemAdmin(): bool
    {
        return $this === self::SystemAdmin;
    }

    /**
     * Get all roles as a [value => label] map for select fields.
     *
     * @return array<string, string>
     */
    public static function options(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn (self $role) => [$role->value => $role->label()])
            ->all();
    }
}
