<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\ContractorHistory;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ContractorHistory>
 */
class ContractorHistoryRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ContractorHistory::class);
    }
}
