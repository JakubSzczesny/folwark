<?php

declare(strict_types=1);

namespace App\Enum;

enum OrderStatus: string
{
    case Expected = 'expected';
    case Approaching = 'approaching';
    case Overdue = 'overdue';
    case Ordered = 'ordered';
    case Skipped = 'skipped';
    case Insufficient = 'insufficient';

    public function label(): string
    {
        return match ($this) {
            self::Expected => 'oczekiwane',
            self::Approaching => 'zbliża się',
            self::Overdue => 'po terminie',
            self::Ordered => 'zamówione',
            self::Skipped => 'pominięte',
            self::Insufficient => 'za mało danych',
        };
    }

    public function tone(): string
    {
        return match ($this) {
            self::Approaching => 'warning',
            self::Overdue => 'danger',
            self::Ordered => 'positive',
            self::Expected => 'info',
            default => 'neutral',
        };
    }

    /**
     * Status ustawiony przez człowieka – trwa do nowej faktury, przeliczenie go nie zmienia.
     */
    public function isManual(): bool
    {
        return self::Ordered === $this || self::Skipped === $this;
    }
}
