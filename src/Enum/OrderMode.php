<?php

declare(strict_types=1);

namespace App\Enum;

enum OrderMode: string
{
    case Continuous = 'continuous';
    case Seasonal = 'seasonal';
    case Manual = 'manual';

    public function label(): string
    {
        return match ($this) {
            self::Continuous => 'ciągły',
            self::Seasonal => 'sezonowy',
            self::Manual => 'ręczny',
        };
    }
}
