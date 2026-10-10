<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Farm;
use App\Entity\FarmMembership;
use App\Entity\User;
use App\Enum\UserRole;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<FarmMembership>
 */
class FarmMembershipRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, FarmMembership::class);
    }

    public function findOneForUser(User $user, int $farmId): ?FarmMembership
    {
        return $this->createQueryBuilder('m')
            ->join('m.farm', 'f')
            ->addSelect('f')
            ->where('m.user = :user')
            ->andWhere('m.farm = :farm')
            ->setParameter('user', $user)
            ->setParameter('farm', $farmId)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * @return list<FarmMembership>
     */
    public function findForUser(User $user): array
    {
        return $this->createQueryBuilder('m')
            ->join('m.farm', 'f')
            ->addSelect('f')
            ->where('m.user = :user')
            ->setParameter('user', $user)
            ->orderBy('f.name')
            ->getQuery()
            ->getResult();
    }

    /**
     * @return list<FarmMembership>
     */
    public function findByFarm(Farm $farm): array
    {
        return $this->createQueryBuilder('m')
            ->join('m.user', 'u')
            ->addSelect('u')
            ->where('m.farm = :farm')
            ->setParameter('farm', $farm)
            ->orderBy('m.role')
            ->addOrderBy('u.fullName')
            ->getQuery()
            ->getResult();
    }

    public function countOwners(Farm $farm): int
    {
        return (int) $this->createQueryBuilder('m')
            ->select('COUNT(m.id)')
            ->join('m.user', 'u')
            ->where('m.farm = :farm')
            ->andWhere('m.role = :owner')
            ->andWhere('u.active = true')
            ->setParameter('farm', $farm)
            ->setParameter('owner', UserRole::Owner)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * @param list<UserRole> $roles
     *
     * @return list<User>
     */
    public function findActiveUsers(Farm $farm, array $roles): array
    {
        if ([] === $roles) {
            return [];
        }

        $memberships = $this->createQueryBuilder('m')
            ->join('m.user', 'u')
            ->addSelect('u')
            ->where('m.farm = :farm')
            ->andWhere('m.role IN (:roles)')
            ->andWhere('u.active = true')
            ->setParameter('farm', $farm)
            ->setParameter('roles', array_map(static fn (UserRole $r): string => $r->value, $roles))
            ->orderBy('u.fullName')
            ->getQuery()
            ->getResult();

        return array_values(array_map(static fn (FarmMembership $m): User => $m->getUser(), $memberships));
    }

    /**
     * @return array<int, int> id gospodarstwa => liczba członków
     */
    public function countMembersByFarm(): array
    {
        $rows = $this->createQueryBuilder('m')
            ->select('IDENTITY(m.farm) AS farm', 'COUNT(m.id) AS cnt')
            ->groupBy('farm')
            ->getQuery()
            ->getScalarResult();

        $counts = [];
        foreach ($rows as $row) {
            $counts[(int) $row['farm']] = (int) $row['cnt'];
        }

        return $counts;
    }
}
