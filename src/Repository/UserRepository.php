<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\User;
use App\Enum\UserRole;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Security\Core\Exception\UnsupportedUserException;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\PasswordUpgraderInterface;

/**
 * @extends ServiceEntityRepository<User>
 */
class UserRepository extends ServiceEntityRepository implements PasswordUpgraderInterface
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, User::class);
    }

    public function upgradePassword(PasswordAuthenticatedUserInterface $user, string $newHashedPassword): void
    {
        if (!$user instanceof User) {
            throw new UnsupportedUserException(\sprintf('Instances of "%s" are not supported.', $user::class));
        }

        $user->setPassword($newHashedPassword);
        $this->getEntityManager()->flush();
    }

    /**
     * @param list<UserRole> $roles
     *
     * @return list<User>
     */
    public function findActiveByRoles(array $roles): array
    {
        if ([] === $roles) {
            return [];
        }

        return $this->createQueryBuilder('u')
            ->where('u.active = true')
            ->andWhere('u.role IN (:roles)')
            ->setParameter('roles', array_map(static fn (UserRole $r): string => $r->value, $roles))
            ->orderBy('u.fullName')
            ->getQuery()
            ->getResult();
    }

    /**
     * @return list<User>
     */
    public function findAllOrdered(): array
    {
        return $this->createQueryBuilder('u')
            ->orderBy('u.active', 'DESC')
            ->addOrderBy('u.fullName')
            ->getQuery()
            ->getResult();
    }

    public function countActiveOwners(): int
    {
        return (int) $this->createQueryBuilder('u')
            ->select('COUNT(u.id)')
            ->where('u.active = true')
            ->andWhere('u.role = :role')
            ->setParameter('role', UserRole::Owner->value)
            ->getQuery()
            ->getSingleScalarResult();
    }
}
