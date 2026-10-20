<?php

declare(strict_types=1);

namespace App\Dashboard;

use App\Analytics\InvoiceStatistics;
use App\Entity\Invoice;
use App\Entity\OrderCycle;
use App\Enum\InvoiceKind;
use App\Order\OrderDeadlines;
use App\Repository\InvoiceRepository;
use App\Repository\OrderCycleRepository;
use App\Security\Permission;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;

/**
 * Kafelki i listy pulpitu – każda część tylko dla roli, która ma do niej dostęp.
 */
final class DashboardData
{
    public function __construct(
        private readonly InvoiceStatistics $statistics,
        private readonly InvoiceRepository $invoices,
        private readonly OrderCycleRepository $cycles,
        private readonly AuthorizationCheckerInterface $auth,
    ) {
    }

    /**
     * @return list<array{key: string, label: string, value: string, detail: ?string, trend: ?float, series: list<float>, route: string, params: array<string, mixed>}>
     */
    public function tiles(?\DateTimeImmutable $today = null): array
    {
        $today ??= new \DateTimeImmutable('today');
        $tiles = [];

        if ($this->auth->isGranted(Permission::INVOICE_VIEW)) {
            $grossCost = $this->statistics->lastTwelveMonths(InvoiceKind::Cost, $today, 'gross');
            $tiles[] = [
                'key' => 'new',
                'label' => 'Nowe faktury',
                'value' => (string) $this->invoices->countNew(),
                'detail' => $this->auth->isGranted(Permission::AMOUNTS_VIEW) ? 'zakupowe w tym miesiącu: '.number_format(end($grossCost) ?: 0, 2, ',', "\u{00A0}").' zł brutto' : null,
                'trend' => null,
                'series' => array_values($grossCost),
                'route' => 'app_invoice_index',
                'params' => ['rodzaj' => 'wszystkie', 'status' => 'new'],
            ];
        }

        if ($this->auth->isGranted(Permission::ORDER_VIEW)) {
            $due = $this->cycles->findDue($today->modify(\sprintf('+%d days', OrderDeadlines::BADGE_DAYS)));
            $tiles[] = [
                'key' => 'orders',
                'label' => 'Zamówienia w ciągu 14 dni',
                'value' => (string) \count($due),
                'detail' => \count(array_filter($due, static fn (OrderCycle $c): bool => 'overdue' === $c->getStatus()->value)) > 0
                    ? \sprintf('w tym po terminie: %d', \count(array_filter($due, static fn (OrderCycle $c): bool => 'overdue' === $c->getStatus()->value)))
                    : null,
                'trend' => null,
                'series' => [],
                'route' => 'app_order_index',
                'params' => [],
            ];
        }

        if ($this->auth->isGranted(Permission::AMOUNTS_VIEW) && $this->auth->isGranted(Permission::INVOICE_VIEW)) {
            foreach ([[InvoiceKind::Cost, 'Koszty', 'zakupowe'], [InvoiceKind::Income, 'Przychody', 'sprzedazowe']] as [$kind, $label, $tab]) {
                $series = $this->statistics->lastTwelveMonths($kind, $today);
                $values = array_values($series);
                $current = $values[\count($values) - 1] ?? 0.0;
                $previous = $values[\count($values) - 2] ?? 0.0;
                $tiles[] = [
                    'key' => $kind->value,
                    'label' => $label.' – ten miesiąc',
                    'value' => number_format($current, 2, ',', "\u{00A0}").' zł',
                    'detail' => 'poprzedni: '.number_format($previous, 2, ',', "\u{00A0}").' zł netto',
                    'trend' => $previous > 0 ? $current / $previous - 1 : null,
                    'series' => $values,
                    'route' => 'app_invoice_index',
                    'params' => ['rodzaj' => $tab, 'okres' => 'ten-miesiac'],
                ];
            }
        }

        return $tiles;
    }

    /**
     * @return list<Invoice>
     */
    public function latestInvoices(int $limit = 5): array
    {
        return $this->auth->isGranted(Permission::INVOICE_VIEW) ? $this->invoices->findLatest($limit) : [];
    }

    /**
     * Najbliższe zamówienia: po terminie i przewidywane najwcześniej.
     *
     * @return list<OrderCycle>
     */
    public function upcomingOrders(int $limit = 5): array
    {
        if (!$this->auth->isGranted(Permission::ORDER_VIEW)) {
            return [];
        }

        return \array_slice($this->cycles->findDue(new \DateTimeImmutable('today +90 days')), 0, $limit);
    }
}
