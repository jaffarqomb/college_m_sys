<?php

namespace App\Enums;

enum AttendanceStatus: string
{
    case Present = 'present';

    case Absent = 'absent';

    case Late = 'late';

    case Excused = 'excused';

    public function label(): string
    {
        return match ($this) {
            self::Present => 'Present',
            self::Absent => 'Absent',
            self::Late => 'Late',
            self::Excused => 'Excused',
        };
    }

    /**
     * Whether this status counts as attendance for percentage purposes.
     * Excused absences do not count against the student.
     */
    public function countsAsAttended(): bool
    {
        return match ($this) {
            self::Present, self::Excused => true,
            self::Absent, self::Late => false,
        };
    }
}
