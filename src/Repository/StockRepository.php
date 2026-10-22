<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Product;
use App\Entity\Stock;
use App\Ui\Sort;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Stock>
 */
class StockRepository extends ServiceEntityRepository
{
    public const array SORT = [
        'towar' => 'p.name',
        'stan' => 's.quantity',
        'cena' => 'CASE WHEN s.quantity > 0 THEN s.value / s.quantity ELSE 0 END',
        'wartosc' => 's.value',
        'ruch' => 's.lastMovementDate',
    ];

    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Stock::class);
    }

    /**
     * Stany niezerowe (albo wszystkie) z towarem i magazynem, posortowane do listy.
     *
     * @param array{warehouse?: ?int, category?: ?int, q?: string, zero?: bool} $filters
     *
     * @return list<Stock>
     */
    public function findForList(array $filters, ?Sort $sort = null): array
    {
        $qb = $this->createQueryBuilder('s')
            ->join('s.product', 'p')
            ->addSelect('p')
            ->leftJoin('p.baseUnit', 'u')
            ->addSelect('u')
            ->join('s.warehouse', 'w')
            ->addSelect('w')
            ->orderBy('w.name')
            ->addOrderBy('p.name');

        if (!($filters['zero'] ?? false)) {
            $qb->andWhere('s.quantity <> 0');
        }
        if (null !== ($filters['warehouse'] ?? null)) {
            $qb->andWhere('s.warehouse = :warehouse')->setParameter('warehouse', $filters['warehouse']);
        }
        if (null !== ($filters['category'] ?? null)) {
            $qb->andWhere('p.category = :category')->setParameter('category', $filters['category']);
        }
        if ('' !== ($filters['q'] ?? '')) {
            $qb->andWhere('p.name LIKE :q OR p.internalCode LIKE :q OR p.catalogNumber LIKE :q')->setParameter('q', '%'.addcslashes($filters['q'], '%_').'%');
        }
        $sort?->apply($qb, 'p.name', 's.id');

        return $qb->getQuery()->getResult();
    }

    /**
     * @return list<Stock>
     */
    public function findForProduct(Product $product): array
    {
        return $this->createQueryBuilder('s')
            ->join('s.warehouse', 'w')
            ->addSelect('w')
            ->where('s.product = :product')
            ->andWhere('s.quantity <> 0')
            ->setParameter('product', $product)
            ->orderBy('w.name')
            ->getQuery()
            ->getResult();
    }
}
