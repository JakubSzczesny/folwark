<?php

declare(strict_types=1);

namespace App\Analytics;

use App\Entity\Invoice;
use App\Entity\InvoiceItem;
use App\Enum\InvoiceKind;
use App\Enum\InvoiceStatus;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Przychody z faktur sprzedażowych: per towar (z przypisanych pozycji) i per odbiorca.
 */
final class IncomeAnalysis
{
    public function __construct(
        private readonly EntityManagerInterface $em,
    ) {
    }

    /**
     * @return list<array{productId: ?int, product: string, unit: string, quantity: float, net: float, avgPrice: ?float}>
     */
    public function byProduct(\DateTimeImmutable $from, \DateTimeImmutable $to): array
    {
        $rows = $this->em->createQueryBuilder()
            ->select('p.id AS productId', 'COALESCE(p.name, it.name) AS product', 'u.symbol AS unit', 'SUM(it.quantityInProductUnit) AS quantity', 'SUM(it.netValue) AS net')
            ->from(InvoiceItem::class, 'it')
            ->join('it.invoice', 'i')
            ->leftJoin('it.product', 'p')
            ->leftJoin('p.baseUnit', 'u')
            ->where('i.kind = :kind')
            ->andWhere('i.status != :skipped')
            ->andWhere('it.beforeCorrection = false')
            ->andWhere('i.issueDate BETWEEN :from AND :to')
            ->setParameter('kind', InvoiceKind::Income)
            ->setParameter('skipped', InvoiceStatus::Skipped)
            ->setParameter('from', $from->format('Y-m-d'))
            ->setParameter('to', $to->format('Y-m-d'))
            ->groupBy('productId', 'product', 'unit')
            ->orderBy('net', 'DESC')
            ->getQuery()
            ->getArrayResult();

        return array_values(array_map(static function (array $row): array {
            $quantity = (float) $row['quantity'];
            $net = (float) $row['net'];

            return [
                'productId' => null !== $row['productId'] ? (int) $row['productId'] : null,
                'product' => (string) $row['product'],
                'unit' => (string) $row['unit'],
                'quantity' => $quantity,
                'net' => $net,
                'avgPrice' => null !== $row['productId'] && $quantity > 0 ? $net / $quantity : null,
            ];
        }, $rows));
    }

    /**
     * @return list<array{contractorId: ?int, contractor: string, invoices: int, net: float, gross: float}>
     */
    public function byCustomer(\DateTimeImmutable $from, \DateTimeImmutable $to): array
    {
        $rows = $this->em->createQueryBuilder()
            ->select('c.id AS contractorId', 'COALESCE(c.name, i.contractorName) AS contractor', 'COUNT(i.id) AS invoices', 'SUM(i.net) AS net', 'SUM(i.gross) AS gross')
            ->from(Invoice::class, 'i')
            ->leftJoin('i.contractor', 'c')
            ->where('i.kind = :kind')
            ->andWhere('i.status != :skipped')
            ->andWhere('i.issueDate BETWEEN :from AND :to')
            ->setParameter('kind', InvoiceKind::Income)
            ->setParameter('skipped', InvoiceStatus::Skipped)
            ->setParameter('from', $from->format('Y-m-d'))
            ->setParameter('to', $to->format('Y-m-d'))
            ->groupBy('contractorId', 'contractor')
            ->orderBy('net', 'DESC')
            ->getQuery()
            ->getArrayResult();

        return array_values(array_map(static fn (array $row): array => [
            'contractorId' => null !== $row['contractorId'] ? (int) $row['contractorId'] : null,
            'contractor' => (string) ($row['contractor'] ?? '—'),
            'invoices' => (int) $row['invoices'],
            'net' => (float) $row['net'],
            'gross' => (float) $row['gross'],
        ], $rows));
    }
}
