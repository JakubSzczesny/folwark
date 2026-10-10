<?php

declare(strict_types=1);

namespace App\Enum;

enum PaymentFrequency: string
{
    case Monthly = 'monthly';
    case Quarterly = 'quarterly';
    case HalfYearly = 'half_yearly';
    case Yearly = 'yearly';

    public function label(): string
    {
        return match ($this) {
            self::Monthly => 'co miesiąc',
            self::Quarterly => 'co kwartał',
            self::HalfYearly => 'co pół roku',
            self::Yearly => 'raz w roku',
        };
    }

    public function months(): int
    {
        return match ($this) {
            self::Monthly => 1,
            self::Quarterly => 3,
            self::HalfYearly => 6,
            self::Yearly => 12,
        };
    }
}
