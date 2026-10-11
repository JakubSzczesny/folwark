<?php

declare(strict_types=1);

namespace App\Order;

use App\Entity\Contractor;
use App\Entity\Invoice;
use App\Entity\InvoiceItem;
use App\Entity\Product;
use App\Enum\InvoiceKind;
use App\Enum\InvoiceStatus;
use App\Enum\InvoiceType;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Zakupy z faktur kosztowych jako dane do przewidywania: per towar (z pozycji) albo per dostawca (z samych kwot).
 */
final class PurchaseHistory
{
    public function __construct(
        private readonly EntityManagerInterface $em,
    ) {
    }

    /**
     * @return array<int, list<Purchase>> id towaru => zakupy (jeden na fakturę)
     */
    public function byProduct(?Product $only = null): array
    {
        $qb = $this->em->createQueryBuilder()
            ->select('IDENTITY(it.product) AS product, i.id AS invoice, i.issueDate AS date, i.type AS type, SUM(it.quantityInProductUnit) AS quantity, SUM(it.netValue) AS amount')
            ->from(InvoiceItem::class, 'it')
            ->join('it.invoice', 'i')
            ->where('it.product IS NOT NULL')
            ->andWhere('i.kind = :kind')
            ->andWhere('i.status != :skipped')
            ->setParameter('kind', InvoiceKind::Cost)
            ->setParameter('skipped', InvoiceStatus::Skipped)
            ->groupBy('it.product, i.id, i.issueDate, i.type');
        if (null !== $only) {
            $qb->andWhere('it.product = :product')->setParameter('product', $only);
        }

        $purchases = [];
        foreach ($qb->getQuery()->getArrayResult() as $row) {
            $purchases[(int) $row['product']][] = self::purchase($row, null !== $row['quantity'] ? (float) $row['quantity'] : null);
        }

        return $purchases;
    }

    /**
     * Dostawcy bez przypisanych towarów – przewidywanie z samych dat i kwot faktur.
     * Faktury „tylko koszt” i pominięte (rachunki, ubezpieczenia) się nie liczą.
     *
     * @return array<int, list<Purchase>> id kontrahenta => zakupy
     */
    public function byContractor(?Contractor $only = null): array
    {
        $qb = $this->em->createQueryBuilder()
            ->select('IDENTITY(i.contractor) AS contractor, i.id AS invoice, i.issueDate AS date, i.type AS type, i.net AS amount')
            ->from(Invoice::class, 'i')
            ->where('i.contractor IS NOT NULL')
            ->andWhere('i.kind = :kind')
            ->andWhere('i.status NOT IN (:excluded)')
            ->andWhere('i.issueDate >= :since')
            ->andWhere(\sprintf('NOT EXISTS (SELECT it.id FROM %s it JOIN it.invoice i2 WHERE i2.contractor = i.contractor AND it.product IS NOT NULL)', InvoiceItem::class))
            ->setParameter('kind', InvoiceKind::Cost)
            ->setParameter('excluded', [InvoiceStatus::CostOnly, InvoiceStatus::Skipped])
            ->setParameter('since', new \DateTimeImmutable('-24 months'));
        if (null !== $only) {
            $qb->andWhere('i.contractor = :contractor')->setParameter('contractor', $only);
        }

        $purchases = [];
        foreach ($qb->getQuery()->getArrayResult() as $row) {
            $purchases[(int) $row['contractor']][] = self::purchase($row, null);
        }

        return $purchases;
    }

    /**
     * @param array<string, mixed> $row
     */
    private static function purchase(array $row, ?float $quantity): Purchase
    {
        $date = $row['date'] instanceof \DateTimeInterface ? \DateTimeImmutable::createFromInterface($row['date']) : new \DateTimeImmutable((string) $row['date']);
        $type = $row['type'] instanceof InvoiceType ? $row['type'] : InvoiceType::tryFrom((string) $row['type']);

        return new Purchase($date, $quantity, (float) $row['amount'], InvoiceType::Correction === $type, (int) $row['invoice']);
    }
}
