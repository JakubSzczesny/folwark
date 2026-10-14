<?php

declare(strict_types=1);

namespace App\Enum;

enum WarehouseType: string
{
    case Silo = 'silo';
    case Clamp = 'clamp';
    case Hall = 'hall';
    case Barn = 'barn';
    case Tank = 'tank';
    case PlantProtection = 'plant_protection';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Silo => 'silos',
            self::Clamp => 'pryzma',
            self::Hall => 'hala',
            self::Barn => 'stodoła',
            self::Tank => 'zbiornik',
            self::PlantProtection => 'magazyn ŚOR',
            self::Other => 'inny',
        };
    }
}
