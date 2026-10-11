<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Notification;
use App\Entity\User;
use App\Enum\NotificationType;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Notification>
 */
class NotificationRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Notification::class);
    }

    public function existsForUser(User $user, string $dedupKey): bool
    {
        return null !== $this->createQueryBuilder('n')
            ->select('n.id')
            ->where('n.user = :user')
            ->andWhere('n.dedupKey = :key')
            ->setParameter('user', $user)
            ->setParameter('key', $dedupKey)
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function countUnread(User $user): int
    {
        return (int) $this->createQueryBuilder('n')
            ->select('COUNT(n.id)')
            ->where('n.user = :user')
            ->andWhere('n.readAt IS NULL')
            ->setParameter('user', $user)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * @return list<Notification>
     */
    public function findLatest(User $user, int $limit = 10): array
    {
        return $this->createQueryBuilder('n')
            ->where('n.user = :user')
            ->setParameter('user', $user)
            ->orderBy('n.createdAt', 'DESC')
            ->addOrderBy('n.id', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * Gasi powiadomienia danego rodzaju – wszystkie albo tylko te, które prowadzą pod wskazany adres.
     */
    public function markReadByType(User $user, NotificationType $type, ?string $link = null): int
    {
        $qb = $this->createQueryBuilder('n')
            ->update()
            ->set('n.readAt', ':now')
            ->where('n.user = :user')
            ->andWhere('n.type = :type')
            ->andWhere('n.readAt IS NULL')
            ->setParameter('now', new \DateTimeImmutable())
            ->setParameter('user', $user)
            ->setParameter('type', $type);
        if (null !== $link) {
            $qb->andWhere('n.link = :link')->setParameter('link', $link);
        }

        return $qb->getQuery()->execute();
    }

    public function markAllRead(User $user): int
    {
        return $this->createQueryBuilder('n')
            ->update()
            ->set('n.readAt', ':now')
            ->where('n.user = :user')
            ->andWhere('n.readAt IS NULL')
            ->setParameter('now', new \DateTimeImmutable())
            ->setParameter('user', $user)
            ->getQuery()
            ->execute();
    }

    /**
     * @param array{type?: ?string, unread?: bool, from?: ?\DateTimeImmutable, to?: ?\DateTimeImmutable} $filters
     */
    public function createListQuery(User $user, array $filters): QueryBuilder
    {
        $qb = $this->createQueryBuilder('n')
            ->where('n.user = :user')
            ->setParameter('user', $user)
            ->orderBy('n.createdAt', 'DESC')
            ->addOrderBy('n.id', 'DESC');

        if (null !== ($type = NotificationType::tryFrom((string) ($filters['type'] ?? '')))) {
            $qb->andWhere('n.type = :type')->setParameter('type', $type);
        }
        if ($filters['unread'] ?? false) {
            $qb->andWhere('n.readAt IS NULL');
        }
        if (null !== ($filters['from'] ?? null)) {
            $qb->andWhere('n.createdAt >= :from')->setParameter('from', $filters['from']);
        }
        if (null !== ($filters['to'] ?? null)) {
            $qb->andWhere('n.createdAt < :to')->setParameter('to', $filters['to']->modify('+1 day'));
        }

        return $qb;
    }

    /**
     * @param list<NotificationType> $types
     *
     * @return list<Notification>
     */
    public function findNotEmailed(User $user, array $types, \DateTimeImmutable $since): array
    {
        if ([] === $types) {
            return [];
        }

        return $this->createQueryBuilder('n')
            ->where('n.user = :user')
            ->andWhere('n.emailedAt IS NULL')
            ->andWhere('n.type IN (:types)')
            ->andWhere('n.createdAt >= :since')
            ->setParameter('user', $user)
            ->setParameter('types', array_map(static fn (NotificationType $t): string => $t->value, $types))
            ->setParameter('since', $since)
            ->orderBy('n.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }
}
