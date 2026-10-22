<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Product;
use App\Ui\Sort;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Product>
 */
class ProductRepository extends ServiceEntityRepository
{
    public const array SORT = [
        'nazwa' => 'p.name',
        'kategoria' => 'c.name',
        'kod' => 'p.internalCode',
        'jm' => 'u.symbol',
        'zakup' => '(SELECT MAX(sort_l.lastOrderDate) FROM App\Entity\OrderCycle sort_l WHERE sort_l.product = p AND sort_l.closedAt IS NULL)',
        'zamowienie' => '(SELECT MIN(sort_n.predictedDate) FROM App\Entity\OrderCycle sort_n WHERE sort_n.product = p AND sort_n.closedAt IS NULL)',
    ];

    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Product::class);
    }

    /**
     * @param array{q: ?string, category: ?int, inactive: bool} $filters
     */
    public function createListQuery(array $filters, Sort $sort): QueryBuilder
    {
        $qb = $this->createQueryBuilder('p')
            ->addSelect('c', 'u')
            ->leftJoin('p.category', 'c')
            ->leftJoin('p.baseUnit', 'u');

        if (!$filters['inactive']) {
            $qb->andWhere('p.active = true');
        }
        if (null !== $filters['category']) {
            $qb->andWhere('p.category = :category OR c.parent = :category')->setParameter('category', $filters['category']);
        }
        if (null !== $filters['q'] && '' !== trim($filters['q'])) {
            $qb->andWhere('LOWER(p.name) LIKE :q OR LOWER(p.internalCode) LIKE :q OR LOWER(p.catalogNumber) LIKE :q')
                ->setParameter('q', '%'.mb_strtolower(trim($filters['q'])).'%');
        }

        return $sort->apply($qb, 'p.name');
    }

    /**
     * @return list<Product>
     */
    public function findActive(): array
    {
        return $this->createQueryBuilder('p')
            ->addSelect('u', 'cv')
            ->leftJoin('p.baseUnit', 'u')
            ->leftJoin('p.conversions', 'cv')
            ->where('p.active = true')
            ->orderBy('p.name')
            ->getQuery()
            ->getResult();
    }

    /**
     * @return list<Product>
     */
    public function search(string $query, int $limit): array
    {
        return $this->createQueryBuilder('p')
            ->where('LOWER(p.name) LIKE :q OR LOWER(p.internalCode) LIKE :q OR LOWER(p.catalogNumber) LIKE :q')
            ->setParameter('q', '%'.mb_strtolower($query).'%')
            ->orderBy('p.active', 'DESC')
            ->addOrderBy('p.name')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }
}
