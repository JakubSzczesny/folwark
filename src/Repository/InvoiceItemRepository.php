<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Contract;
use App\Entity\InvoiceItem;
use App\Enum\InvoiceStatus;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<InvoiceItem>
 */
class InvoiceItemRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, InvoiceItem::class);
    }

    /**
     * @return list<InvoiceItem>
     */
    public function findForContract(Contract $contract): array
    {
        return $this->createQueryBuilder('it')
            ->join('it.invoice', 'i')
            ->addSelect('i')
            ->where('i.contract = :contract')
            ->andWhere('i.status != :skipped')
            ->setParameter('contract', $contract)
            ->setParameter('skipped', InvoiceStatus::Skipped)
            ->orderBy('i.issueDate')
            ->getQuery()
            ->getResult();
    }
}
