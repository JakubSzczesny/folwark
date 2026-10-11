<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\MachineInfoCategory;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<MachineInfoCategory>
 */
class MachineInfoCategoryRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, MachineInfoCategory::class);
    }

    /**
     * @return list<MachineInfoCategory>
     */
    public function findAllOrdered(): array
    {
        return $this->findBy([], ['position' => 'ASC', 'id' => 'ASC']);
    }

    public function nextPosition(?MachineInfoCategory $parent): int
    {
        $qb = $this->createQueryBuilder('c')->select('MAX(c.position)');
        null === $parent ? $qb->where('c.parent IS NULL') : $qb->where('c.parent = :parent')->setParameter('parent', $parent);

        return (int) $qb->getQuery()->getSingleScalarResult() + 1;
    }

    public function isUsed(MachineInfoCategory $category): bool
    {
        if (!$category->getChildren()->isEmpty()) {
            return true;
        }

        return (int) $this->getEntityManager()->createQueryBuilder()
            ->select('COUNT(e.id)')
            ->from('App\Entity\MachineInfoEntry', 'e')
            ->where('e.category = :category')
            ->setParameter('category', $category)
            ->getQuery()
            ->getSingleScalarResult() > 0;
    }
}
