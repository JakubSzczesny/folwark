<?php

declare(strict_types=1);

namespace App\Order;

/**
 * Zamówienie: zakupy w odstępie kilku dni połączone w jedno.
 */
final class MergedOrder
{
    /** @var list<int> */
    public array $invoiceIds = [];

    public function __construct(
        public readonly \DateTimeImmutable $date,
        public \DateTimeImmutable $lastPurchase,
        public ?float $quantity,
        public float $amount,
    ) {
    }

    public function add(Purchase $purchase): void
    {
        $this->lastPurchase = max($this->lastPurchase, $purchase->date);
        if (null !== $purchase->quantity) {
            $this->quantity = ($this->quantity ?? 0.0) + $purchase->quantity;
        }
        $this->amount += $purchase->amount;
        if (null !== $purchase->invoiceId) {
            $this->invoiceIds[] = $purchase->invoiceId;
        }
    }

    public function unitPrice(): ?float
    {
        return null !== $this->quantity && $this->quantity > 0 ? $this->amount / $this->quantity : null;
    }
}
