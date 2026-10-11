<?php

declare(strict_types=1);

namespace App\Enum;

enum TareSource: string
{
    case Manual = 'manual';
    case Measured = 'measured';

    public function label(): string
    {
        return match ($this) {
            self::Manual => 'wpisana ręcznie',
            self::Measured => 'pomiar na wadze',
        };
    }
}
