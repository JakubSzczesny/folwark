<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\RecentChoice;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<RecentChoice>
 */
class RecentChoiceRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, RecentChoice::class);
    }

    /**
     * @return list<int>
     */
    public function findRecentIds(User $user, string $kind, int $limit): array
    {
        $rows = $this->createQueryBuilder('r')
            ->select('r.entityId')
            ->where('r.user = :user')
            ->andWhere('r.kind = :kind')
            ->setParameter('user', $user)
            ->setParameter('kind', $kind)
            ->orderBy('r.usedAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getScalarResult();

        return array_values(array_map(static fn (array $row): int => (int) $row['entityId'], $rows));
    }
}
