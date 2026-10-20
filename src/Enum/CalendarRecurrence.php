<?php

declare(strict_types=1);

namespace App\Enum;

enum CalendarRecurrence: string
{
    case None = 'none';
    case Daily = 'daily';
    case Weekly = 'weekly';
    case Monthly = 'monthly';
    case Yearly = 'yearly';

    public function label(): string
    {
        return match ($this) {
            self::None => 'Nie powtarza się',
            self::Daily => 'Codziennie',
            self::Weekly => 'Co tydzień',
            self::Monthly => 'Co miesiąc',
            self::Yearly => 'Co roku',
        };
    }
}
