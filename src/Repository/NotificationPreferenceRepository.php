<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\NotificationPreference;
use App\Entity\User;
use App\Enum\NotificationType;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<NotificationPreference>
 */
class NotificationPreferenceRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, NotificationPreference::class);
    }

    /**
     * @return list<int>
     */
    public function findDisabledUserIds(NotificationType $type): array
    {
        $rows = $this->createQueryBuilder('p')
            ->select('IDENTITY(p.user) AS userId')
            ->where('p.type = :type')
            ->andWhere('p.enabled = false')
            ->setParameter('type', $type)
            ->getQuery()
            ->getScalarResult();

        return array_values(array_map(static fn (array $row): int => (int) $row['userId'], $rows));
    }

    /**
     * @return array<string, NotificationPreference> klucz = wartość typu
     */
    public function findForUser(User $user): array
    {
        $map = [];
        foreach ($this->findBy(['user' => $user]) as $preference) {
            $map[$preference->getType()->value] = $preference;
        }

        return $map;
    }

    /**
     * @return list<array{user: User, types: list<NotificationType>}>
     */
    public function findEmailTypesByUser(): array
    {
        /** @var list<NotificationPreference> $preferences */
        $preferences = $this->createQueryBuilder('p')
            ->join('p.user', 'u')
            ->addSelect('u')
            ->where('p.email = true')
            ->andWhere('p.enabled = true')
            ->andWhere('u.active = true')
            ->getQuery()
            ->getResult();

        $grouped = [];
        foreach ($preferences as $preference) {
            $user = $preference->getUser();
            $key = (int) $user->getId();
            $grouped[$key] ??= ['user' => $user, 'types' => []];
            $grouped[$key]['types'][] = $preference->getType();
        }

        return array_values($grouped);
    }
}
