<?php

declare(strict_types=1);

namespace App\Analytics;

use App\Entity\InvoiceItem;
use App\Entity\OrderCycle;
use App\Enum\InvoiceKind;
use App\Enum\InvoiceStatus;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Szacowane zużycie z zakupów: ile towaru kupujemy średnio na dzień, w ostatnich 12 miesiącach
 * i rok wcześniej, oraz bieżące tempo z cyklu zamówień.
 */
final class UsageAnalysis
{
    public function __construct(
        private readonly EntityManagerInterface $em,
    ) {
    }

    /**
     * @return list<array{productId: int, product: string, unit: string, current: float, previous: float, perDay: float, perDayPrevious: float, change: ?float, cycleUsage: ?float}>
     */
    public function products(\DateTimeImmutable $today): array
    {
        $yearAgo = $today->modify('-12 months');
        $twoYearsAgo = $today->modify('-24 months');

        $rows = $this->em->createQueryBuilder()
            ->select('p.id AS productId', 'p.name AS product', 'u.symbol AS unit', 'SUM(CASE WHEN i.issueDate > :yearAgo THEN it.quantityInProductUnit ELSE 0 END) AS current', 'SUM(CASE WHEN i.issueDate <= :yearAgo THEN it.quantityInProductUnit ELSE 0 END) AS previous')
            ->from(InvoiceItem::class, 'it')
            ->join('it.invoice', 'i')
            ->join('it.product', 'p')
            ->leftJoin('p.baseUnit', 'u')
            ->where('i.kind = :kind')
            ->andWhere('i.status != :skipped')
            ->andWhere('it.beforeCorrection = false')
            ->andWhere('i.issueDate > :from AND i.issueDate <= :today')
            ->setParameter('kind', InvoiceKind::Cost)
            ->setParameter('skipped', InvoiceStatus::Skipped)
            ->setParameter('yearAgo', $yearAgo->format('Y-m-d'))
            ->setParameter('from', $twoYearsAgo->format('Y-m-d'))
            ->setParameter('today', $today->format('Y-m-d'))
            ->groupBy('productId', 'product', 'unit')
            ->orderBy('product')
            ->getQuery()
            ->getArrayResult();

        $cycles = [];
        foreach ($this->em->getRepository(OrderCycle::class)->findBy(['closedAt' => null]) as $cycle) {
            if (null !== ($id = $cycle->getProduct()?->getId()) && null !== $cycle->getDailyUsage()) {
                $cycles[$id] = (float) $cycle->getDailyUsage();
            }
        }

        $days = max(1, (int) $yearAgo->diff($today)->format('%a'));
        $result = [];
        foreach ($rows as $row) {
            $current = (float) $row['current'];
            $previous = (float) $row['previous'];
            if ($current <= 0 && $previous <= 0) {
                continue;
            }
            $result[] = [
                'productId' => (int) $row['productId'],
                'product' => (string) $row['product'],
                'unit' => (string) $row['unit'],
                'current' => $current,
                'previous' => $previous,
                'perDay' => $current / $days,
                'perDayPrevious' => $previous / $days,
                'change' => $previous > 0 ? $current / $previous - 1 : null,
                'cycleUsage' => $cycles[(int) $row['productId']] ?? null,
            ];
        }

        return $result;
    }
}
