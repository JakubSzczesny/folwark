<?php

declare(strict_types=1);

namespace App\Enum;

enum InvoiceStatus: string
{
    case New = 'new';
    case Read = 'read';
    case GoodsAssigned = 'goods_assigned';
    case CostOnly = 'cost_only';
    case Skipped = 'skipped';

    public function label(): string
    {
        return match ($this) {
            self::New => 'Nowa',
            self::Read => 'Przeczytana',
            self::GoodsAssigned => 'Towary przypisane',
            self::CostOnly => 'Tylko koszt',
            self::Skipped => 'Pominięta',
        };
    }

    public function tone(): string
    {
        return match ($this) {
            self::New => 'new',
            self::Read => 'info',
            self::GoodsAssigned, self::CostOnly => 'positive',
            self::Skipped => 'neutral',
        };
    }

    /**
     * Statusy, przy których pozycji nie przypisuje się do towarów.
     *
     * @return list<self>
     */
    public static function withoutGoods(): array
    {
        return [self::CostOnly, self::Skipped];
    }

    public function isProcessed(): bool
    {
        return \in_array($this, [self::GoodsAssigned, self::CostOnly, self::Skipped], true);
    }
}
