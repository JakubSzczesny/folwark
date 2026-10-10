<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\ContractorBankAccount;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ContractorBankAccount>
 */
class ContractorBankAccountRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ContractorBankAccount::class);
    }
}
