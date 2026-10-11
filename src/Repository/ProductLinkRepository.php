<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Product;
use App\Entity\ProductLink;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ProductLink>
 */
class ProductLinkRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ProductLink::class);
    }

    /**
     * @return array<int, int> id kategorii => liczba linków towaru
     */
    public function countByCategory(Product $product): array
    {
        $rows = $this->createQueryBuilder('l')
            ->select('IDENTITY(l.category) AS category, COUNT(l.id) AS links')
            ->where('l.product = :product')
            ->setParameter('product', $product)
            ->groupBy('l.category')
            ->getQuery()
            ->getArrayResult();

        $counts = [];
        foreach ($rows as $row) {
            $counts[(int) $row['category']] = (int) $row['links'];
        }

        return $counts;
    }

    /**
     * @return list<ProductLink>
     */
    public function findForProduct(Product $product): array
    {
        return $this->findBy(['product' => $product], ['addedAt' => 'ASC']);
    }

    /**
     * Ostatnio używane kategorie linków – na górę listy wyboru.
     *
     * @return list<int>
     */
    public function recentCategoryIds(int $limit = 5): array
    {
        $rows = $this->createQueryBuilder('l')
            ->select('IDENTITY(l.category) AS category, MAX(l.addedAt) AS last')
            ->groupBy('l.category')
            ->orderBy('last', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getArrayResult();

        return array_values(array_map(static fn (array $row): int => (int) $row['category'], $rows));
    }
}
