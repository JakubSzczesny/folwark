<?php

declare(strict_types=1);

namespace App\Order;

use App\Enum\OrderMode;
use App\Enum\OrderRegularity;

final readonly class Prediction
{
    /**
     * @param list<MergedOrder> $orders od najstarszego
     */
    public function __construct(
        public array $orders,
        public ?OrderMode $mode = null,
        public ?OrderRegularity $regularity = null,
        public ?int $intervalDays = null,
        public ?int $spreadDays = null,
        public ?\DateTimeImmutable $predictedDate = null,
        public ?\DateTimeImmutable $windowFrom = null,
        public ?\DateTimeImmutable $windowTo = null,
        public ?\DateTimeImmutable $reminderDate = null,
        public ?float $typicalQuantity = null,
        public ?float $typicalAmount = null,
        public ?float $dailyUsage = null,
        public ?float $medianUnitPrice = null,
    ) {
    }

    public function isSufficient(): bool
    {
        return null !== $this->predictedDate;
    }

    public function lastOrder(): ?MergedOrder
    {
        return [] === $this->orders ? null : $this->orders[array_key_last($this->orders)];
    }

    /**
     * Zmiana ceny: ostatnia cena jednostkowa względem mediany z 12 miesięcy (np. 0,08 = +8 %).
     */
    public function priceChange(): ?float
    {
        $last = $this->lastOrder()?->unitPrice();
        if (null === $last || null === $this->medianUnitPrice || $this->medianUnitPrice <= 0) {
            return null;
        }

        return $last / $this->medianUnitPrice - 1;
    }
}
