<?php

namespace App\Enums;

enum LetterGrade: string
{
    case A = 'A';

    case B = 'B';

    case C = 'C';

    case D = 'D';

    case F = 'F';

    public function label(): string
    {
        return $this->value;
    }

    /**
     * The minimum percentage (100-point scale) required for the grade.
     */
    public function minPercentage(): float
    {
        return match ($this) {
            self::A => 90.0,
            self::B => 80.0,
            self::C => 70.0,
            self::D => 60.0,
            self::F => 0.0,
        };
    }

    /**
     * The GPA points awarded for this grade.
     */
    public function points(): float
    {
        return match ($this) {
            self::A => 4.0,
            self::B => 3.0,
            self::C => 2.0,
            self::D => 1.0,
            self::F => 0.0,
        };
    }
}
