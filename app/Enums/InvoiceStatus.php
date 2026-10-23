<?php

namespace App\Enums;

enum InvoiceStatus: string
{
    case Draft = 'draft';

    case Unpaid = 'unpaid';

    case Partial = 'partial';

    case Paid = 'paid';

    case Overdue = 'overdue';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Draft',
            self::Unpaid => 'Unpaid',
            self::Partial => 'Partially paid',
            self::Paid => 'Paid',
            self::Overdue => 'Overdue',
        };
    }

    /**
     * Statuses used for the Filament filter and outstanding scoping.
     *
     * @return list<self>
     */
    public static function outstanding(): array
    {
        return [self::Unpaid, self::Partial, self::Overdue];
    }

    /**
     * Whether this status can be re-evaluated after a payment or time passes.
     */
    public function isLive(): bool
    {
        return in_array($this, [self::Unpaid, self::Partial, self::Overdue], true);
    }

    /**
     * The color token used by badges in the panel and portal.
     */
    public function color(): string
    {
        return match ($this) {
            self::Draft => 'gray',
            self::Unpaid => 'primary',
            self::Partial => 'warning',
            self::Paid => 'success',
            self::Overdue => 'danger',
        };
    }
}
