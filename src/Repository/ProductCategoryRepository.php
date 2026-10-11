<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\ProductCategory;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ProductCategory>
 */
class ProductCategoryRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ProductCategory::class);
    }

    /**
     * Kategorie w kolejności drzewa: każda główna, a pod nią jej podkategorie.
     *
     * @return list<ProductCategory>
     */
    public function findTreeOrdered(): array
    {
        $all = $this->findBy([], ['position' => 'ASC', 'name' => 'ASC']);
        $byParent = [];
        foreach ($all as $category) {
            $byParent[$category->getParent()?->getId() ?? 0][] = $category;
        }

        $ordered = [];
        $walk = static function (int $parent) use (&$walk, &$ordered, $byParent): void {
            foreach ($byParent[$parent] ?? [] as $category) {
                $ordered[] = $category;
                $walk((int) $category->getId());
            }
        };
        $walk(0);

        return $ordered;
    }
}
