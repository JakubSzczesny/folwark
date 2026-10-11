<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\ProductLink;
use App\Entity\ProductLinkCategory;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ProductLinkCategory>
 */
class ProductLinkCategoryRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ProductLinkCategory::class);
    }

    /**
     * @return list<ProductLinkCategory>
     */
    public function findAllOrdered(): array
    {
        return $this->findBy([], ['position' => 'ASC', 'id' => 'ASC']);
    }

    public function nextPosition(?ProductLinkCategory $parent): int
    {
        $qb = $this->createQueryBuilder('c')->select('MAX(c.position)');
        null === $parent ? $qb->where('c.parent IS NULL') : $qb->where('c.parent = :parent')->setParameter('parent', $parent);

        return (int) $qb->getQuery()->getSingleScalarResult() + 1;
    }

    /**
     * Kategoria jest w użyciu, gdy ma podkategorie albo linki (przy jakimkolwiek towarze).
     */
    public function isUsed(ProductLinkCategory $category): bool
    {
        if ($this->count(['parent' => $category]) > 0) {
            return true;
        }

        return $this->getEntityManager()->getRepository(ProductLink::class)->count(['category' => $category]) > 0;
    }
}
