<?php

declare(strict_types=1);

namespace App\Enum;

enum ContractStatus: string
{
    case Active = 'active';
    case Finished = 'finished';
    case Terminated = 'terminated';

    public function label(): string
    {
        return match ($this) {
            self::Active => 'Aktywna',
            self::Finished => 'Zakończona',
            self::Terminated => 'Rozwiązana',
        };
    }

    public function tone(): string
    {
        return match ($this) {
            self::Active => 'positive',
            self::Finished => 'neutral',
            self::Terminated => 'neutral',
        };
    }
}
