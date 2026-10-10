<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\KsefSync;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<KsefSync>
 */
class KsefSyncRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, KsefSync::class);
    }

    public function findLastStarted(): ?KsefSync
    {
        return $this->createQueryBuilder('s')
            ->orderBy('s.startedAt', 'DESC')
            ->addOrderBy('s.id', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function findLastFinished(): ?KsefSync
    {
        return $this->createQueryBuilder('s')
            ->where('s.finishedAt IS NOT NULL')
            ->orderBy('s.startedAt', 'DESC')
            ->addOrderBy('s.id', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * @return list<KsefSync>
     */
    public function findRecent(int $limit = 10): array
    {
        return $this->createQueryBuilder('s')
            ->leftJoin('s.user', 'u')
            ->addSelect('u')
            ->orderBy('s.startedAt', 'DESC')
            ->addOrderBy('s.id', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * Początek bieżącej serii nieudanych synchronizacji (od ostatniej udanej).
     */
    public function findErrorStreakStart(): ?\DateTimeImmutable
    {
        $lastOk = $this->createQueryBuilder('s')
            ->select('MAX(s.startedAt)')
            ->where('s.finishedAt IS NOT NULL')
            ->andWhere('s.error IS NULL')
            ->getQuery()
            ->getSingleScalarResult();

        $qb = $this->createQueryBuilder('s')
            ->select('MIN(s.startedAt)')
            ->where('s.error IS NOT NULL');
        if (null !== $lastOk) {
            $qb->andWhere('s.startedAt > :ok')->setParameter('ok', $lastOk);
        }
        $start = $qb->getQuery()->getSingleScalarResult();

        return null !== $start ? new \DateTimeImmutable((string) $start) : null;
    }
}
