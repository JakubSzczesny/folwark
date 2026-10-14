<?php

declare(strict_types=1);

namespace App\Analytics;

use App\Entity\Invoice;
use App\Enum\InvoiceKind;
use App\Enum\InvoiceStatus;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Sumy faktur w miesiącach – podstawa pulpitu i analiz. Liczone z faktur (netto), nie ze stanów;
 * korekty wchodzą ze swoim znakiem, pominięte faktury nie liczą się wcale.
 */
final class InvoiceStatistics
{
    public function __construct(
        private readonly EntityManagerInterface $em,
    ) {
    }

    /**
     * @param array{category?: ?int, contractor?: ?int, costCenter?: ?int} $filters
     *
     * @return array<string, float> „RRRR-MM” => suma netto, dla każdego miesiąca zakresu (także zerowych)
     */
    public function monthly(InvoiceKind $kind, \DateTimeImmutable $from, \DateTimeImmutable $to, array $filters = [], string $field = 'net'): array
    {
        $qb = $this->em->createQueryBuilder()
            ->select(\sprintf('SUBSTRING(i.issueDate, 1, 7) AS month, SUM(i.%s) AS total', 'gross' === $field ? 'gross' : 'net'))
            ->from(Invoice::class, 'i')
            ->where('i.kind = :kind')
            ->andWhere('i.status != :skipped')
            ->andWhere('i.issueDate BETWEEN :from AND :to')
            ->setParameter('kind', $kind)
            ->setParameter('skipped', InvoiceStatus::Skipped)
            ->setParameter('from', $from->modify('first day of this month')->setTime(0, 0))
            ->setParameter('to', $to->modify('last day of this month')->setTime(0, 0))
            ->groupBy('month');

        if (null !== ($filters['category'] ?? null)) {
            $qb->andWhere('i.costCategory = :category')->setParameter('category', $filters['category']);
        }
        if (null !== ($filters['contractor'] ?? null)) {
            $qb->andWhere('i.contractor = :contractor')->setParameter('contractor', $filters['contractor']);
        }
        if (null !== ($filters['costCenter'] ?? null)) {
            $qb->andWhere('i.costCenter = :center')->setParameter('center', $filters['costCenter']);
        }

        $totals = array_fill_keys(self::months($from, $to), 0.0);
        foreach ($qb->getQuery()->getArrayResult() as $row) {
            if (isset($totals[$row['month']])) {
                $totals[$row['month']] = round((float) $row['total'], 2);
            }
        }

        return $totals;
    }

    /**
     * Ostatnie 12 miesięcy łącznie z bieżącym.
     *
     * @return array<string, float>
     */
    public function lastTwelveMonths(InvoiceKind $kind, ?\DateTimeImmutable $today = null, string $field = 'net'): array
    {
        $today ??= new \DateTimeImmutable('today');

        return $this->monthly($kind, $today->modify('first day of this month')->modify('-11 months'), $today, [], $field);
    }

    /**
     * @return list<string> „RRRR-MM” od $from do $to
     */
    public static function months(\DateTimeImmutable $from, \DateTimeImmutable $to): array
    {
        $months = [];
        for ($month = $from->modify('first day of this month')->setTime(0, 0); $month <= $to; $month = $month->modify('+1 month')) {
            $months[] = $month->format('Y-m');
        }

        return $months;
    }
}
