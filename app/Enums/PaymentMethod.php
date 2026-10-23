<?php

namespace App\Enums;

enum PaymentMethod: string
{
    case Manual = 'manual';

    case Tap = 'tap';

    public function label(): string
    {
        return match ($this) {
            self::Manual => 'Manual (cash / bank transfer)',
            self::Tap => 'Tap (online)',
        };
    }
}
