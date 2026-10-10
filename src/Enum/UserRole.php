<?php

declare(strict_types=1);

namespace App\Enum;

enum UserRole: string
{
    case Owner = 'ROLE_OWNER';
    case Worker = 'ROLE_WORKER';
    case Accountant = 'ROLE_ACCOUNTANT';

    public function label(): string
    {
        return match ($this) {
            self::Owner => 'Właściciel',
            self::Worker => 'Pracownik',
            self::Accountant => 'Księgowa',
        };
    }
}
