<?php

declare(strict_types=1);

namespace App\Twig\Components;

use App\Analytics\InvoiceStatistics;
use App\Controller\DashboardController;
use App\Dashboard\AttentionList;
use App\Dashboard\DashboardData;
use App\Dashboard\DashboardWidgets;
use App\Entity\Invoice;
use App\Entity\OrderCycle;
use App\Enum\InvoiceKind;
use Symfony\UX\Chartjs\Builder\ChartBuilderInterface;
use Symfony\UX\Chartjs\Model\Chart;
use Symfony\UX\LiveComponent\Attribute\AsLiveComponent;
use Symfony\UX\LiveComponent\DefaultActionTrait;

/**
 * Pulpit odświeżany na żywo. Widżety i ich kolejność wybiera użytkownik (DashboardWidgets).
 */
#[AsLiveComponent]
final class Dashboard
{
    use DefaultActionTrait;

    public function __construct(
        private readonly AttentionList $attention,
        private readonly DashboardData $data,
        private readonly DashboardWidgets $widgets,
        private readonly InvoiceStatistics $statistics,
        private readonly ChartBuilderInterface $charts,
    ) {
    }

    /**
     * @return list<array{key: string, label: string}>
     */
    public function getWidgets(): array
    {
        return array_map(
            static fn (string $key): array => ['key' => $key, 'label' => DashboardWidgets::CATALOG[$key]['label']],
            $this->widgets->active(),
        );
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

    public function getChart(): Chart
    {
        return DashboardController::chart(
            $this->charts,
            $this->statistics->lastTwelveMonths(InvoiceKind::Cost),
            $this->statistics->lastTwelveMonths(InvoiceKind::Income),
        );
    }

    public function getRefreshedAt(): \DateTimeImmutable
    {
        return new \DateTimeImmutable();
    }
}
