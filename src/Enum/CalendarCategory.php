<?php

declare(strict_types=1);

namespace App\Enum;

enum CalendarCategory: string
{
    case Field = 'field';
    case Animals = 'animals';
    case Machines = 'machines';
    case Finance = 'finance';
    case Meeting = 'meeting';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Field => 'Prace polowe',
            self::Animals => 'Zwierzęta',
            self::Machines => 'Maszyny',
            self::Finance => 'Finanse i urzędy',
            self::Meeting => 'Spotkanie',
            self::Other => 'Inne',
        };
    }
}
