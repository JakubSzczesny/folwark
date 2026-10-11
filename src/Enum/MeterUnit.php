<?php

declare(strict_types=1);

namespace App\Enum;

enum MeterUnit: string
{
    case Hours = 'mth';
    case Kilometers = 'km';

    public function label(): string
    {
        return match ($this) {
            self::Hours => 'mth',
            self::Kilometers => 'km',
        };
    }

    public function longLabel(): string
    {
        return match ($this) {
            self::Hours => 'motogodziny',
            self::Kilometers => 'kilometry',
        };
    }
}
