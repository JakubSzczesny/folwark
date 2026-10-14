<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\MilkProduction;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<MilkProduction>
 */
class MilkProductionRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, MilkProduction::class);
    }
}
