<?php

declare(strict_types=1);

namespace App\Enum;

enum DocumentStatus: string
{
    case Draft = 'draft';
    case Approved = 'approved';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'szkic',
            self::Approved => 'zatwierdzony',
            self::Cancelled => 'anulowany',
        };
    }

    public function tone(): string
    {
        return match ($this) {
            self::Draft => 'warning',
            self::Approved => 'positive',
            self::Cancelled => 'neutral',
        };
    }
}
