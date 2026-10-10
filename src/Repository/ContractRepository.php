<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Contract;
use App\Entity\Contractor;
use App\Enum\ContractKind;
use App\Enum\ContractStatus;
use App\Search\GlobalSearch;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Contract>
 */
class ContractRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Contract::class);
    }

    /**
     * Aktywne umowy kontrahenta danego rodzaju, obowiązujące w podanym dniu.
     *
     * @return list<Contract>
     */
    public function findActiveFor(Contractor $contractor, ?ContractKind $kind = null, ?\DateTimeImmutable $date = null): array
    {
        $qb = $this->createQueryBuilder('k')
            ->where('k.contractor = :c')
            ->andWhere('k.status = :active')
            ->setParameter('c', $contractor)
            ->setParameter('active', ContractStatus::Active)
            ->orderBy('k.validFrom', 'DESC');

        if (null !== $kind) {
            $qb->andWhere('k.kind = :kind')->setParameter('kind', $kind);
        }
        if (null !== $date) {
            $qb->andWhere('k.validFrom IS NULL OR k.validFrom <= :date')
                ->andWhere('k.validTo IS NULL OR k.validTo >= :date')
                ->setParameter('date', $date->format('Y-m-d'));
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * @param array{contractor?: ?int, kind?: ?ContractKind, status?: ?ContractStatus, expiring?: bool, today?: \DateTimeImmutable, days?: int} $filters
     */
    public function createListQuery(array $filters): QueryBuilder
    {
        $qb = $this->createQueryBuilder('k')
            ->join('k.contractor', 'c')
            ->addSelect('c')
            ->orderBy('k.status')
            ->addOrderBy('k.validTo')
            ->addOrderBy('k.number');

        if (null !== ($filters['contractor'] ?? null)) {
            $qb->andWhere('k.contractor = :contractor')->setParameter('contractor', $filters['contractor']);
        }
        if (null !== ($filters['kind'] ?? null)) {
            $qb->andWhere('k.kind = :kind')->setParameter('kind', $filters['kind']);
        }
        if (null !== ($filters['status'] ?? null)) {
            $qb->andWhere('k.status = :status')->setParameter('status', $filters['status']);
        }
        if ($filters['expiring'] ?? false) {
            $today = $filters['today'] ?? new \DateTimeImmutable('today');
            $qb->andWhere('k.status = :active')
                ->andWhere('k.validTo BETWEEN :today AND :limit')
                ->setParameter('active', ContractStatus::Active)
                ->setParameter('today', $today->format('Y-m-d'))
                ->setParameter('limit', $today->modify(\sprintf('+%d days', $filters['days'] ?? 30))->format('Y-m-d'));
        }

        return $qb;
    }

    /**
     * @return list<Contract>
     */
    public function findExpiring(\DateTimeImmutable $today, int $days): array
    {
        return $this->createListQuery(['expiring' => true, 'today' => $today, 'days' => $days])->getQuery()->getResult();
    }

    /**
     * @return list<Contract>
     */
    public function findActiveLeases(): array
    {
        return $this->createQueryBuilder('k')
            ->join('k.contractor', 'c')
            ->addSelect('c')
            ->where('k.kind = :lease')
            ->andWhere('k.status = :active')
            ->andWhere('k.firstPaymentDate IS NOT NULL')
            ->andWhere('k.paymentFrequency IS NOT NULL')
            ->setParameter('lease', ContractKind::Lease)
            ->setParameter('active', ContractStatus::Active)
            ->getQuery()
            ->getResult();
    }

    /**
     * @return list<Contract>
     */
    public function search(string $query, int $limit): array
    {
        return $this->createQueryBuilder('k')
            ->join('k.contractor', 'c')
            ->addSelect('c')
            ->where('LOWER(k.number) LIKE :q OR LOWER(k.subject) LIKE :q OR LOWER(c.name) LIKE :q')
            ->setParameter('q', GlobalSearch::likePattern($query))
            ->orderBy('k.status')
            ->addOrderBy('k.validTo', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * @param list<int> $contractorIds
     *
     * @return array<int, int> id kontrahenta => liczba aktywnych umów
     */
    public function countActiveByContractor(array $contractorIds): array
    {
        if ([] === $contractorIds) {
            return [];
        }

        $rows = $this->createQueryBuilder('k')
            ->select('IDENTITY(k.contractor) AS contractor', 'COUNT(k.id) AS cnt')
            ->where('k.contractor IN (:ids)')
            ->andWhere('k.status = :active')
            ->setParameter('ids', $contractorIds)
            ->setParameter('active', ContractStatus::Active)
            ->groupBy('contractor')
            ->getQuery()
            ->getScalarResult();

        $counts = [];
        foreach ($rows as $row) {
            $counts[(int) $row['contractor']] = (int) $row['cnt'];
        }

        return $counts;
    }
}
