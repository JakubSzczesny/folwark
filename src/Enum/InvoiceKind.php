<?php

declare(strict_types=1);

namespace App\Enum;

enum InvoiceKind: string
{
    case Cost = 'cost';
    case Income = 'income';

    public function label(): string
    {
        return match ($this) {
            self::Cost => 'Zakupowa',
            self::Income => 'Sprzedażowa',
        };
    }

    public function plural(): string
    {
        return match ($this) {
            self::Cost => 'Zakupowe',
            self::Income => 'Sprzedażowe',
        };
    }

    public function opposite(): self
    {
        return self::Cost === $this ? self::Income : self::Cost;
    }
}
