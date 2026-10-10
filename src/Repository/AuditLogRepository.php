<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\AuditLog;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<AuditLog>
 */
class AuditLogRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, AuditLog::class);
    }

    public function createListQuery(?string $entity): QueryBuilder
    {
        $qb = $this->createQueryBuilder('l')
            ->leftJoin('l.user', 'u')
            ->addSelect('u')
            ->orderBy('l.createdAt', 'DESC')
            ->addOrderBy('l.id', 'DESC');

        if (null !== $entity) {
            $qb->andWhere('l.entity = :entity')->setParameter('entity', $entity);
        }

        return $qb;
    }

    /**
     * @return list<AuditLog>
     */
    public function findForEntity(string $entity, int|string $id, int $limit = 50): array
    {
        return $this->createQueryBuilder('l')
            ->leftJoin('l.user', 'u')
            ->addSelect('u')
            ->where('l.entity = :entity')
            ->andWhere('l.entityId = :id')
            ->setParameter('entity', $entity)
            ->setParameter('id', (string) $id)
            ->orderBy('l.createdAt', 'DESC')
            ->addOrderBy('l.id', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * @return list<string>
     */
    public function entityNames(): array
    {
        $rows = $this->createQueryBuilder('l')
            ->select('DISTINCT l.entity')
            ->orderBy('l.entity')
            ->getQuery()
            ->getScalarResult();

        return array_column($rows, 'entity');
    }
}
