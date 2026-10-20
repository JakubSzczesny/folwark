<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Contract;
use App\Entity\InvoiceItem;
use App\Enum\InvoiceKind;
use App\Enum\InvoiceStatus;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<InvoiceItem>
 */
class InvoiceItemRepository extends ServiceEntityRepository
{
    /** Warunek DQL na pozycję (alias „it”) bez towaru – jak InvoiceItem::isUnassigned(). */
    public const string UNASSIGNED = 'it.product IS NULL AND it.skipped = false AND it.beforeCorrection = false';

    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, InvoiceItem::class);
    }

    /**
     * Liczba pozycji bez przypisanego towaru na fakturach zakupowych (por. Invoice::countUnassignedItems()).
     *
     * @param list<int> $invoiceIds
     *
     * @return array<int, int> id faktury => liczba pozycji
     */
    public function countUnassigned(array $invoiceIds): array
    {
        if ([] === $invoiceIds) {
            return [];
        }

        $rows = $this->createQueryBuilder('it')
            ->select('IDENTITY(it.invoice) AS invoice', 'COUNT(it.id) AS cnt')
            ->join('it.invoice', 'i')
            ->where('it.invoice IN (:ids)')
            ->andWhere(self::UNASSIGNED)
            ->andWhere('i.kind = :cost')
            ->andWhere('i.status NOT IN (:withoutGoods)')
            ->setParameter('ids', $invoiceIds)
            ->setParameter('cost', InvoiceKind::Cost)
            ->setParameter('withoutGoods', InvoiceStatus::withoutGoods())
            ->groupBy('it.invoice')
            ->getQuery()
            ->getScalarResult();

        $counts = [];
        foreach ($rows as $row) {
            $counts[(int) $row['invoice']] = (int) $row['cnt'];
        }

        return $counts;
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
