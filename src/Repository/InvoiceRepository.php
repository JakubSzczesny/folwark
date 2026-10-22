<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Contractor;
use App\Entity\Invoice;
use App\Entity\InvoiceItem;
use App\Enum\InvoiceKind;
use App\Enum\InvoiceStatus;
use App\Enum\PaymentStatus;
use App\Invoice\InvoiceFilter;
use App\Search\GlobalSearch;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Invoice>
 */
class InvoiceRepository extends ServiceEntityRepository
{
    public const array SORT = [
        'data' => 'i.issueDate',
        'numer' => 'i.number',
        'kontrahent' => 'i.contractorName',
        'netto' => 'i.net',
        'brutto' => 'i.gross',
        'termin' => 'i.paymentDue',
        'status' => "CASE i.status WHEN 'new' THEN 0 WHEN 'read' THEN 1 WHEN 'goods_assigned' THEN 2 WHEN 'cost_only' THEN 3 ELSE 4 END",
    ];

    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Invoice::class);
    }

    public function existsByKsefNumber(string $ksefNumber): bool
    {
        return null !== $this->createQueryBuilder('i')
            ->select('i.id')
            ->where('i.ksefNumber = :n')
            ->setParameter('n', $ksefNumber)
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function findCorrected(?string $ksefNumber, ?string $number, ?string $contractorNip): ?Invoice
    {
        if (null !== $ksefNumber) {
            $invoice = $this->findOneBy(['ksefNumber' => $ksefNumber]);
            if (null !== $invoice) {
                return $invoice;
            }
        }

        if (null !== $number && null !== $contractorNip) {
            return $this->findOneBy(['number' => $number, 'contractorNip' => $contractorNip]);
        }

        return null;
    }

    /**
     * @return list<Invoice>
     */
    public function findCorrectionsOf(Invoice $invoice): array
    {
        return $this->createQueryBuilder('i')
            ->where('i.correctedKsefNumber = :n')
            ->andWhere('i.correctedInvoice IS NULL')
            ->setParameter('n', $invoice->getKsefNumber())
            ->getQuery()
            ->getResult();
    }

    public function createListQuery(InvoiceFilter $filter): QueryBuilder
    {
        $qb = $this->filtered($filter)
            ->leftJoin('i.contractor', 'c')
            ->addSelect('c');

        return $filter->sort->apply($qb, 'i.id');
    }

    /**
     * Identyfikatory wszystkich faktur spełniających filtry, w kolejności listy.
     *
     * @return list<int>
     */
    public function ids(InvoiceFilter $filter): array
    {
        $qb = $filter->sort->apply($this->filtered($filter)->select('i.id'), 'i.id');

        return array_map('intval', array_column($qb->getQuery()->getScalarResult(), 'id'));
    }

    /**
     * @return array<string, array{net: float, vat: float, gross: float, count: int}> waluta => sumy
     */
    public function sums(InvoiceFilter $filter): array
    {
        $rows = $this->filtered($filter)
            ->select('i.currency AS currency', 'SUM(i.net) AS net', 'SUM(i.vat) AS vat', 'SUM(i.gross) AS gross', 'COUNT(i.id) AS cnt')
            ->groupBy('i.currency')
            ->getQuery()
            ->getScalarResult();

        $sums = [];
        foreach ($rows as $row) {
            $sums[(string) $row['currency']] = [
                'net' => (float) $row['net'],
                'vat' => (float) $row['vat'],
                'gross' => (float) $row['gross'],
                'count' => (int) $row['cnt'],
            ];
        }
        uksort($sums, static fn (string $a, string $b): int => ['PLN' !== $a, $a] <=> ['PLN' !== $b, $b]);

        return $sums;
    }

    /**
     * @return array{prev: ?int, next: ?int}
     */
    public function neighbours(InvoiceFilter $filter, Invoice $invoice): array
    {
        $ids = $this->ids($filter);
        $index = array_search($invoice->getId(), $ids, true);
        if (false === $index) {
            return ['prev' => null, 'next' => null];
        }

        return ['prev' => $ids[$index - 1] ?? null, 'next' => $ids[$index + 1] ?? null];
    }

    public function countNew(?InvoiceKind $kind = null): int
    {
        $qb = $this->createQueryBuilder('i')
            ->select('COUNT(i.id)')
            ->where('i.readAt IS NULL');
        if (null !== $kind) {
            $qb->andWhere('i.kind = :kind')->setParameter('kind', $kind);
        }

        return (int) $qb->getQuery()->getSingleScalarResult();
    }

    /**
     * Nieopłacone faktury zakupowe z terminem płatności do podanego dnia (także po terminie).
     *
     * @return list<Invoice>
     */
    public function findUnpaidDueBefore(\DateTimeImmutable $date): array
    {
        return $this->createQueryBuilder('i')
            ->leftJoin('i.contractor', 'c')
            ->addSelect('c')
            ->where('i.kind = :cost')
            ->andWhere('i.payment = :unpaid')
            ->andWhere('i.status != :skipped')
            ->andWhere('i.paymentDue IS NOT NULL')
            ->andWhere('i.paymentDue <= :date')
            ->setParameter('cost', InvoiceKind::Cost)
            ->setParameter('unpaid', PaymentStatus::Unpaid)
            ->setParameter('skipped', InvoiceStatus::Skipped)
            ->setParameter('date', $date->format('Y-m-d'))
            ->orderBy('i.paymentDue')
            ->getQuery()
            ->getResult();
    }

    /**
     * @return list<Invoice>
     */
    public function findLatest(int $limit, ?InvoiceKind $kind = null): array
    {
        $qb = $this->createQueryBuilder('i')
            ->leftJoin('i.contractor', 'c')
            ->addSelect('c')
            ->orderBy('i.permanentStorageAt', 'DESC')
            ->addOrderBy('i.id', 'DESC')
            ->setMaxResults($limit);
        if (null !== $kind) {
            $qb->where('i.kind = :kind')->setParameter('kind', $kind);
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * @return list<Invoice>
     */
    public function search(string $query, int $limit): array
    {
        return $this->createQueryBuilder('i')
            ->leftJoin('i.contractor', 'c')
            ->addSelect('c')
            ->where('LOWER(i.number) LIKE :q OR LOWER(i.contractorName) LIKE :q OR LOWER(c.name) LIKE :q OR LOWER(c.shortName) LIKE :q OR i.ksefNumber LIKE :q')
            ->setParameter('q', GlobalSearch::likePattern($query))
            ->orderBy('i.issueDate', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * Sumy roczne kosztów i przychodów kontrahentów (tylko waluta PLN).
     *
     * @param list<int> $contractorIds
     *
     * @return array<int, array{cost: float, income: float, last: ?string}>
     */
    public function yearlyTotalsByContractor(array $contractorIds, int $year): array
    {
        if ([] === $contractorIds) {
            return [];
        }

        $rows = $this->createQueryBuilder('i')
            ->select('IDENTITY(i.contractor) AS contractor', 'i.kind AS kind', 'SUM(i.net) AS net', 'MAX(i.issueDate) AS last')
            ->where('i.contractor IN (:ids)')
            ->andWhere('i.issueDate BETWEEN :from AND :to')
            ->andWhere('i.status != :skipped')
            ->setParameter('ids', $contractorIds)
            ->setParameter('from', \sprintf('%d-01-01', $year))
            ->setParameter('to', \sprintf('%d-12-31', $year))
            ->setParameter('skipped', InvoiceStatus::Skipped)
            ->groupBy('contractor', 'kind')
            ->getQuery()
            ->getScalarResult();

        $totals = [];
        foreach ($rows as $row) {
            $id = (int) $row['contractor'];
            $totals[$id] ??= ['cost' => 0.0, 'income' => 0.0, 'last' => null];
            $totals[$id][InvoiceKind::Cost->value === $row['kind'] ? 'cost' : 'income'] += (float) $row['net'];
        }

        $lastRows = $this->createQueryBuilder('i')
            ->select('IDENTITY(i.contractor) AS contractor', 'MAX(i.issueDate) AS last')
            ->where('i.contractor IN (:ids)')
            ->setParameter('ids', $contractorIds)
            ->groupBy('contractor')
            ->getQuery()
            ->getScalarResult();
        foreach ($lastRows as $row) {
            $id = (int) $row['contractor'];
            $totals[$id] ??= ['cost' => 0.0, 'income' => 0.0, 'last' => null];
            $totals[$id]['last'] = (string) $row['last'];
        }

        return $totals;
    }

    /**
     * @return list<Invoice>
     */
    public function findForContractor(Contractor $contractor, InvoiceKind $kind, int $year): array
    {
        return $this->createQueryBuilder('i')
            ->where('i.contractor = :c')
            ->andWhere('i.kind = :kind')
            ->andWhere('i.issueDate BETWEEN :from AND :to')
            ->setParameter('c', $contractor)
            ->setParameter('kind', $kind)
            ->setParameter('from', \sprintf('%d-01-01', $year))
            ->setParameter('to', \sprintf('%d-12-31', $year))
            ->orderBy('i.issueDate', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * @return list<int>
     */
    public function yearsForContractor(Contractor $contractor): array
    {
        $rows = $this->createQueryBuilder('i')
            ->select('i.issueDate AS d')
            ->where('i.contractor = :c')
            ->setParameter('c', $contractor)
            ->getQuery()
            ->getScalarResult();

        $years = array_values(array_unique(array_map(static fn (array $r): int => (int) substr((string) $r['d'], 0, 4), $rows)));
        rsort($years);

        return $years;
    }

    private function filtered(InvoiceFilter $filter): QueryBuilder
    {
        $qb = $this->createQueryBuilder('i');

        if (null !== ($kind = $filter->kind())) {
            $qb->andWhere('i.kind = :kind')->setParameter('kind', $kind);
        }

        [$from, $to] = $filter->dateRange();
        if (null !== $from) {
            $qb->andWhere('i.issueDate >= :from')->setParameter('from', $from->format('Y-m-d'));
        }
        if (null !== $to) {
            $qb->andWhere('i.issueDate <= :to')->setParameter('to', $to->format('Y-m-d'));
        }
        if (null !== $filter->contractorId) {
            $qb->andWhere('i.contractor = :contractor')->setParameter('contractor', $filter->contractorId);
        }
        if (InvoiceStatus::New === $filter->status) {
            $qb->andWhere('i.readAt IS NULL');
        } elseif (null !== $filter->status) {
            $qb->andWhere('i.status = :status')->setParameter('status', $filter->status);
        }
        if (null !== $filter->payment) {
            $qb->andWhere('i.payment = :payment')->setParameter('payment', $filter->payment);
        }
        if (null !== $filter->costCategoryId) {
            $qb->andWhere('i.costCategory = :category')->setParameter('category', $filter->costCategoryId);
        }
        if ($filter->unassigned) {
            $qb->andWhere('i.kind = :costKind AND i.status NOT IN (:withoutGoods)')
                ->andWhere(\sprintf('EXISTS (SELECT 1 FROM %s it WHERE it.invoice = i AND %s)', InvoiceItem::class, InvoiceItemRepository::UNASSIGNED))
                ->setParameter('costKind', InvoiceKind::Cost)
                ->setParameter('withoutGoods', InvoiceStatus::withoutGoods());
        }
        if (null !== $filter->amountFrom) {
            $qb->andWhere('i.gross >= :amountFrom')->setParameter('amountFrom', $filter->amountFrom);
        }
        if (null !== $filter->amountTo) {
            $qb->andWhere('i.gross <= :amountTo')->setParameter('amountTo', $filter->amountTo);
        }
        if (null !== $filter->text) {
            $qb->andWhere('LOWER(i.number) LIKE :text OR i.ksefNumber LIKE :text')
                ->setParameter('text', GlobalSearch::likePattern($filter->text));
        }

        return $qb;
    }
}
