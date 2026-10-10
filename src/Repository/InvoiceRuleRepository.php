<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\InvoiceRule;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<InvoiceRule>
 */
class InvoiceRuleRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, InvoiceRule::class);
    }

    public function findActiveForNip(string $nip): ?InvoiceRule
    {
        return $this->findOneBy(['contractorNip' => $nip, 'active' => true]);
    }
}
