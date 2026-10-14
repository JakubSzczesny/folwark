<?php

declare(strict_types=1);

namespace App\Analytics;

use App\Entity\Invoice;
use App\Entity\InvoiceItem;
use App\Enum\InvoiceKind;
use App\Enum\InvoiceStatus;
use App\Order\OrderPredictor;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Ceny jednostkowe z pozycji faktur przypisanych do towarów (w jednostce podstawowej towaru).
 * Ostrzegamy, gdy cena jest wyższa od mediany z 12 miesięcy przed zakupem o więcej niż 10 %.
 */
final class PriceAnalysis
{
    public const float THRESHOLD = 0.10;

    public function __construct(
        private readonly EntityManagerInterface $em,
    ) {
    }

    /**
     * Ostrzeżenia dla pozycji jednej faktury.
     *
     * @return array<int, array{median: float, price: float, percent: float}> id pozycji => ostrzeżenie
     */
    public function alertsForInvoice(Invoice $invoice): array
    {
        if (InvoiceKind::Cost !== $invoice->getKind()) {
            return [];
        }

        $products = [];
        foreach ($invoice->getItems() as $item) {
            if (null !== $item->getProduct() && null !== self::unitPrice($item->getNetValue(), $item->getQuantityInProductUnit())) {
                $products[] = $item->getProduct()->getId();
            }
        }
        if ([] === $products) {
            return [];
        }

        $history = [];
        foreach ($this->points($invoice->getIssueDate()->modify('-12 months'), $invoice->getIssueDate(), array_values(array_unique($products))) as $point) {
            if ($point['invoiceId'] !== $invoice->getId() && $point['date'] < $invoice->getIssueDate()) {
                $history[$point['productId']][] = $point['price'];
            }
        }

        $alerts = [];
        foreach ($invoice->getItems() as $item) {
            $price = self::unitPrice($item->getNetValue(), $item->getQuantityInProductUnit());
            $prices = $history[$item->getProduct()?->getId()] ?? [];
            if (null !== $price && \count($prices) >= 2 && null !== ($alert = self::compare($price, $prices))) {
                $alerts[(int) $item->getId()] = $alert;
            }
        }

        return $alerts;
    }

    /**
     * Pozycje z ostatnich dni droższe od mediany.
     *
     * @return list<array{date: \DateTimeImmutable, invoiceId: int, invoiceNumber: string, contractor: string, productId: int, product: string, unit: string, price: float, median: float, percent: float}>
     */
    public function recentAlerts(\DateTimeImmutable $today, int $days = 90): array
    {
        $since = $today->modify(\sprintf('-%d days', $days));
        $points = $this->points($since->modify('-12 months'), $today);

        $byProduct = [];
        foreach ($points as $point) {
            $byProduct[$point['productId']][] = $point;
        }

        $alerts = [];
        foreach ($points as $point) {
            if ($point['date'] < $since) {
                continue;
            }
            $prices = [];
            foreach ($byProduct[$point['productId']] as $other) {
                if ($other['date'] < $point['date'] && $other['date'] >= $point['date']->modify('-12 months')) {
                    $prices[] = $other['price'];
                }
            }
            if (\count($prices) >= 2 && null !== ($alert = self::compare($point['price'], $prices))) {
                $alerts[] = $alert + $point;
            }
        }
        usort($alerts, static fn (array $a, array $b): int => $b['date'] <=> $a['date']);

        return $alerts;
    }

    /**
     * Zestawienie cen towarów z 12 miesięcy: ostatnia, min, maks, mediana, liczba dostawców.
     *
     * @return list<array{productId: int, product: string, unit: string, lastDate: \DateTimeImmutable, lastPrice: float, lastSupplier: string, min: float, max: float, median: float, suppliers: int, purchases: int, change: float}>
     */
    public function products(\DateTimeImmutable $today): array
    {
        $groups = [];
        foreach ($this->points($today->modify('-12 months'), $today) as $point) {
            $group = $groups[$point['productId']] ?? ['productId' => $point['productId'], 'product' => $point['product'], 'unit' => $point['unit'], 'lastDate' => $point['date'], 'lastPrice' => $point['price'], 'lastSupplier' => $point['contractor'], 'prices' => [], 'suppliers' => []];
            if ($point['date'] >= $group['lastDate']) {
                $group['lastDate'] = $point['date'];
                $group['lastPrice'] = $point['price'];
                $group['lastSupplier'] = $point['contractor'];
            }
            $group['prices'][] = $point['price'];
            $group['suppliers'][$point['contractor']] = true;
            $groups[$point['productId']] = $group;
        }

        $rows = [];
        foreach ($groups as $group) {
            $median = (float) OrderPredictor::median($group['prices']);
            $rows[] = [
                'productId' => $group['productId'],
                'product' => $group['product'],
                'unit' => $group['unit'],
                'lastDate' => $group['lastDate'],
                'lastPrice' => $group['lastPrice'],
                'lastSupplier' => $group['lastSupplier'],
                'min' => min($group['prices']),
                'max' => max($group['prices']),
                'median' => $median,
                'suppliers' => \count($group['suppliers']),
                'purchases' => \count($group['prices']),
                'change' => $median > 0 ? $group['lastPrice'] / $median - 1 : 0.0,
            ];
        }
        usort($rows, static fn (array $a, array $b): int => strcasecmp($a['product'], $b['product']));

        return $rows;
    }

    /**
     * @param list<float> $prices
     *
     * @return array{median: float, price: float, percent: float}|null
     */
    public static function compare(float $price, array $prices): ?array
    {
        $median = OrderPredictor::median($prices);
        if (null === $median || $median <= 0 || $price <= $median * (1 + self::THRESHOLD)) {
            return null;
        }

        return ['median' => $median, 'price' => $price, 'percent' => $price / $median - 1];
    }

    private static function unitPrice(?string $net, ?string $quantity): ?float
    {
        return null !== $net && (float) $quantity > 0 ? (float) $net / (float) $quantity : null;
    }

    /**
     * @param list<int|null>|null $products
     *
     * @return list<array{date: \DateTimeImmutable, invoiceId: int, invoiceNumber: string, contractor: string, productId: int, product: string, unit: string, price: float}>
     */
    private function points(\DateTimeImmutable $from, \DateTimeImmutable $to, ?array $products = null): array
    {
        $qb = $this->em->createQueryBuilder()
            ->select('it.netValue AS net', 'it.quantityInProductUnit AS quantity', 'i.id AS invoiceId', 'i.number AS invoiceNumber', 'i.issueDate AS date', 'COALESCE(c.shortName, c.name, i.contractorName) AS contractor', 'p.id AS productId', 'p.name AS product', 'u.symbol AS unit')
            ->from(InvoiceItem::class, 'it')
            ->join('it.invoice', 'i')
            ->join('it.product', 'p')
            ->leftJoin('p.baseUnit', 'u')
            ->leftJoin('i.contractor', 'c')
            ->where('i.kind = :kind')
            ->andWhere('i.status != :skipped')
            ->andWhere('it.beforeCorrection = false')
            ->andWhere('it.quantityInProductUnit > 0')
            ->andWhere('i.issueDate BETWEEN :from AND :to')
            ->setParameter('kind', InvoiceKind::Cost)
            ->setParameter('skipped', InvoiceStatus::Skipped)
            ->setParameter('from', $from->format('Y-m-d'))
            ->setParameter('to', $to->format('Y-m-d'))
            ->orderBy('i.issueDate');
        if (null !== $products) {
            $qb->andWhere('p.id IN (:products)')->setParameter('products', $products);
        }

        $points = [];
        foreach ($qb->getQuery()->getArrayResult() as $row) {
            $price = self::unitPrice($row['net'], $row['quantity']);
            if (null === $price || $price <= 0) {
                continue;
            }
            $points[] = [
                'date' => $row['date'],
                'invoiceId' => (int) $row['invoiceId'],
                'invoiceNumber' => (string) $row['invoiceNumber'],
                'contractor' => (string) ($row['contractor'] ?? '—'),
                'productId' => (int) $row['productId'],
                'product' => (string) $row['product'],
                'unit' => (string) $row['unit'],
                'price' => $price,
            ];
        }

        return $points;
    }
}
