<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\CostCenter;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<CostCenter>
 */
class CostCenterRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, CostCenter::class);
    }

    /**
     * @return list<CostCenter>
     */
    public function findActive(): array
    {
        return $this->findBy(['active' => true], ['type' => 'ASC', 'name' => 'ASC']);
    }
}
