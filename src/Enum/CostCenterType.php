<?php

declare(strict_types=1);

namespace App\Enum;

enum CostCenterType: string
{
    case FieldSeason = 'field_season';
    case HerdGroup = 'herd_group';
    case Machine = 'machine';
    case General = 'general';

    public function label(): string
    {
        return match ($this) {
            self::FieldSeason => 'Pole / sezon',
            self::HerdGroup => 'Grupa stada',
            self::Machine => 'Maszyna',
            self::General => 'Ogólne',
        };
    }
}
