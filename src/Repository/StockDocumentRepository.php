<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Invoice;
use App\Entity\StockDocument;
use App\Enum\DocumentStatus;
use App\Enum\DocumentType;
use App\Search\GlobalSearch;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<StockDocument>
 */
class StockDocumentRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, StockDocument::class);
    }

    /**
     * @param array{type?: ?DocumentType, status?: ?DocumentStatus, warehouse?: ?int, product?: ?int, from?: ?\DateTimeImmutable, to?: ?\DateTimeImmutable, q?: string} $filters
     */
    public function createListQuery(array $filters): QueryBuilder
    {
        $qb = $this->createQueryBuilder('d')
            ->leftJoin('d.contractor', 'c')
            ->addSelect('c')
            ->leftJoin('d.sourceWarehouse', 'sw')
            ->addSelect('sw')
            ->leftJoin('d.targetWarehouse', 'tw')
            ->addSelect('tw')
            ->orderBy('d.date', 'DESC')
            ->addOrderBy('d.id', 'DESC');

        if (null !== ($filters['type'] ?? null)) {
            $qb->andWhere('d.type = :type')->setParameter('type', $filters['type']);
        }
        if (null !== ($filters['status'] ?? null)) {
            $qb->andWhere('d.status = :status')->setParameter('status', $filters['status']);
        }
        if (null !== ($filters['warehouse'] ?? null)) {
            $qb->andWhere('d.sourceWarehouse = :warehouse OR d.targetWarehouse = :warehouse')->setParameter('warehouse', $filters['warehouse']);
        }
        if (null !== ($filters['product'] ?? null)) {
            $qb->andWhere('EXISTS (SELECT 1 FROM App\Entity\StockDocumentItem i WHERE i.document = d AND i.product = :product)')
                ->setParameter('product', $filters['product']);
        }
        if (null !== ($filters['from'] ?? null)) {
            $qb->andWhere('d.date >= :from')->setParameter('from', $filters['from']->format('Y-m-d'));
        }
        if (null !== ($filters['to'] ?? null)) {
            $qb->andWhere('d.date <= :to')->setParameter('to', $filters['to']->format('Y-m-d'));
        }
        if ('' !== ($filters['q'] ?? '')) {
            $qb->andWhere('d.number LIKE :q OR c.name LIKE :q OR d.notes LIKE :q')->setParameter('q', '%'.addcslashes($filters['q'], '%_').'%');
        }

        return $qb;
    }

    /**
     * @return list<StockDocument>
     */
    public function search(string $query, int $limit): array
    {
        return $this->createQueryBuilder('d')
            ->leftJoin('d.contractor', 'c')
            ->addSelect('c')
            ->where('LOWER(d.number) LIKE :q OR LOWER(c.name) LIKE :q OR LOWER(d.notes) LIKE :q')
            ->setParameter('q', GlobalSearch::likePattern($query))
            ->orderBy('d.date', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * Kolejny numer w miesiącu dokumentu – numeracja osobna dla każdego typu.
     */
    public function nextSequence(DocumentType $type, \DateTimeImmutable $date): int
    {
        $max = $this->createQueryBuilder('d')
            ->select('MAX(d.sequence)')
            ->where('d.type = :type')
            ->andWhere('d.date BETWEEN :from AND :to')
            ->andWhere('d.sequence IS NOT NULL')
            ->setParameter('type', $type)
            ->setParameter('from', $date->modify('first day of this month')->format('Y-m-d'))
            ->setParameter('to', $date->modify('last day of this month')->format('Y-m-d'))
            ->getQuery()
            ->getSingleScalarResult();

        return (int) $max + 1;
    }

    /**
     * @return list<StockDocument>
     */
    public function findForInvoice(Invoice $invoice): array
    {
        return $this->createQueryBuilder('d')
            ->where('d.invoice = :invoice')
            ->setParameter('invoice', $invoice)
            ->orderBy('d.id')
            ->getQuery()
            ->getResult();
    }
}
