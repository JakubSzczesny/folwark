<?php

declare(strict_types=1);

namespace App\Twig\Components;

use App\Analytics\InvoiceStatistics;
use App\Calendar\CalendarEntry;
use App\Calendar\CalendarFeed;
use App\Controller\DashboardController;
use App\Dashboard\AttentionList;
use App\Dashboard\DashboardData;
use App\Dashboard\DashboardWidgets;
use App\Entity\Invoice;
use App\Entity\OrderCycle;
use App\Entity\User;
use App\Enum\InvoiceKind;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\UX\Chartjs\Builder\ChartBuilderInterface;
use Symfony\UX\Chartjs\Model\Chart;
use Symfony\UX\LiveComponent\Attribute\AsLiveComponent;
use Symfony\UX\LiveComponent\Attribute\LiveAction;
use Symfony\UX\LiveComponent\Attribute\LiveArg;
use Symfony\UX\LiveComponent\Attribute\LiveProp;
use Symfony\UX\LiveComponent\DefaultActionTrait;

/**
 * Pulpit odświeżany na żywo. Widżety i ich kolejność wybiera użytkownik (DashboardWidgets).
 */
#[AsLiveComponent]
final class Dashboard
{
    use DefaultActionTrait;

    #[LiveProp(writable: true)]
    public int $attentionPage = 1;

    public function __construct(
        private readonly AttentionList $attention,
        private readonly DashboardData $data,
        private readonly DashboardWidgets $widgets,
        private readonly InvoiceStatistics $statistics,
        private readonly ChartBuilderInterface $charts,
        private readonly CalendarFeed $calendar,
        private readonly Security $security,
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
     * @return array{items: list<\App\Dashboard\AttentionItem>, total: int, page: int, pages: int}
     */
    public function getAttention(): array
    {
        $attention = $this->attention->build($this->attentionPage);
        $this->attentionPage = $attention['page'];

        return $attention;
    }

    #[LiveAction]
    public function showAttentionPage(#[LiveArg] int $page): void
    {
        $this->attentionPage = max(1, $page);
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

    /**
     * @return list<CalendarEntry> najbliższe 14 dni, najwyżej 8 pozycji
     */
    public function getUpcomingEvents(): array
    {
        $today = new \DateTimeImmutable('today');
        $user = $this->security->getUser();

        return \array_slice($this->calendar->entries($today, $today->modify('+14 days'), $user instanceof User ? $user : null), 0, 8);
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
