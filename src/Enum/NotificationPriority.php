<?php

declare(strict_types=1);

namespace App\Enum;

enum NotificationPriority: string
{
    case Info = 'info';
    case Warning = 'warning';
    case Urgent = 'urgent';

    public function label(): string
    {
        return match ($this) {
            self::Info => 'Informacja',
            self::Warning => 'Ostrzeżenie',
            self::Urgent => 'Pilne',
        };
    }

    public function tone(): string
    {
        return match ($this) {
            self::Info => 'neutral',
            self::Warning => 'warning',
            self::Urgent => 'danger',
        };
    }
}
