<?php

declare(strict_types=1);

namespace App\Enum;

enum PaymentStatus: string
{
    case Unpaid = 'unpaid';
    case Paid = 'paid';
    case Unknown = 'unknown';

    public function label(): string
    {
        return match ($this) {
            self::Unpaid => 'Nieopłacona',
            self::Paid => 'Opłacona',
            self::Unknown => 'Nieznana',
        };
    }

    public function tone(): string
    {
        return match ($this) {
            self::Unpaid => 'warning',
            self::Paid => 'positive',
            self::Unknown => 'neutral',
        };
    }
}
