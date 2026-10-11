<?php

declare(strict_types=1);

namespace App\Enum;

enum OrderRegularity: string
{
    case Regular = 'regular';
    case Moderate = 'moderate';
    case Irregular = 'irregular';

    public function label(): string
    {
        return match ($this) {
            self::Regular => 'regularny',
            self::Moderate => 'umiarkowanie regularny',
            self::Irregular => 'nieregularny',
        };
    }

    public function tone(): string
    {
        return match ($this) {
            self::Regular => 'positive',
            self::Moderate => 'info',
            self::Irregular => 'neutral',
        };
    }

    /**
     * Przypomnienia domyślnie tylko dla regularnych i umiarkowanie regularnych.
     */
    public function notifies(): bool
    {
        return self::Irregular !== $this;
    }
}
