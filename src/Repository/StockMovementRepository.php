<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Product;
use App\Entity\StockDocument;
use App\Entity\StockMovement;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<StockMovement>
 */
class StockMovementRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, StockMovement::class);
    }

    /**
     * @return list<StockMovement>
     */
    public function findForDocument(StockDocument $document): array
    {
        return $this->findBy(['document' => $document], ['id' => 'ASC']);
    }

    /**
     * @return list<StockMovement>
     */
    public function findRecentForProduct(Product $product, int $limit = 20): array
    {
        return $this->createQueryBuilder('m')
            ->join('m.document', 'd')
            ->addSelect('d')
            ->join('m.warehouse', 'w')
            ->addSelect('w')
            ->where('m.product = :product')
            ->setParameter('product', $product)
            ->orderBy('m.date', 'DESC')
            ->addOrderBy('m.id', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }
}
