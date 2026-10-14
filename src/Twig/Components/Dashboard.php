<?php

declare(strict_types=1);

namespace App\Twig\Components;

use App\Dashboard\AttentionList;
use App\Dashboard\DashboardData;
use App\Entity\Invoice;
use App\Entity\OrderCycle;
use Symfony\UX\LiveComponent\Attribute\AsLiveComponent;
use Symfony\UX\LiveComponent\DefaultActionTrait;

/**
 * Pulpit odświeżany na żywo: kafelki, „Wymaga uwagi”, ostatnie faktury i najbliższe zamówienia.
 */
#[AsLiveComponent]
final class Dashboard
{
    use DefaultActionTrait;

    public function __construct(
        private readonly AttentionList $attention,
        private readonly DashboardData $data,
    ) {
    }

    /**
     * @return array{items: list<\App\Dashboard\AttentionItem>, total: int}
     */
    public function getAttention(): array
    {
        return $this->attention->build();
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function getTiles(): array
    {
        return $this->data->tiles();
    }

    /**
     * @return list<Invoice>
     */
    public function getLatestInvoices(): array
    {
        return $this->data->latestInvoices();
    }

    /**
     * @return list<OrderCycle>
     */
    public function getUpcomingOrders(): array
    {
        return $this->data->upcomingOrders();
    }

    public function getRefreshedAt(): \DateTimeImmutable
    {
        return new \DateTimeImmutable();
    }
}
