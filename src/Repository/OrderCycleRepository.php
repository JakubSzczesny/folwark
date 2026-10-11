<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Contractor;
use App\Entity\OrderCycle;
use App\Entity\OrderSettings;
use App\Entity\Product;
use App\Enum\OrderStatus;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<OrderCycle>
 */
class OrderCycleRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, OrderCycle::class);
    }

    /**
     * Otwarte cykle, klucz „p:{id}” (towar) albo „c:{id}” (dostawca).
     *
     * @return array<string, OrderCycle>
     */
    public function findOpenIndexed(): array
    {
        $indexed = [];
        foreach ($this->createQueryBuilder('o')->where('o.closedAt IS NULL')->getQuery()->getResult() as $cycle) {
            $indexed[self::key($cycle)] = $cycle;
        }

        return $indexed;
    }

    public function findOpenFor(Product|Contractor $subject): ?OrderCycle
    {
        return $this->findOneBy([$subject instanceof Product ? 'product' : 'contractor' => $subject, 'closedAt' => null]);
    }

    /**
     * Otwarte, nieukryte cykle do ekranu „Zamówienia”.
     *
     * @param array{category: ?int, contractor: ?int, regularity: ?string, mode: ?string, hidden: bool} $filters
     *
     * @return list<OrderCycle>
     */
    public function findForList(array $filters): array
    {
        $qb = $this->open()
            ->addSelect('p', 'c', 'u', 'pc')
            ->leftJoin('p.baseUnit', 'u')
            ->leftJoin('p.category', 'pc');

        if (!$filters['hidden']) {
            $qb->andWhere(\sprintf('NOT EXISTS (SELECT s.id FROM %s s WHERE s.hidden = true AND (s.product = o.product OR s.contractor = o.contractor))', OrderSettings::class));
        }
        if (null !== $filters['category']) {
            $qb->andWhere('p.category = :category OR pc.parent = :category')->setParameter('category', $filters['category']);
        }
        if (null !== $filters['contractor']) {
            $qb->andWhere('o.contractor = :contractor OR EXISTS (SELECT it.id FROM App\Entity\InvoiceItem it JOIN it.invoice i WHERE it.product = o.product AND i.contractor = :contractor)')
                ->setParameter('contractor', $filters['contractor']);
        }
        if (null !== $filters['regularity']) {
            $qb->andWhere('o.regularity = :regularity')->setParameter('regularity', $filters['regularity']);
        }
        if (null !== $filters['mode']) {
            $qb->andWhere('o.mode = :mode')->setParameter('mode', $filters['mode']);
        }

        return $qb->orderBy('o.predictedDate', 'ASC')->getQuery()->getResult();
    }

    /**
     * Cykle do przypomnień i pulpitu: po terminie albo z przypomnieniem do podanej daty.
     *
     * @return list<OrderCycle>
     */
    public function findDue(\DateTimeImmutable $until): array
    {
        return $this->open()
            ->andWhere('o.status IN (:statuses)')
            ->andWhere('o.predictedDate <= :until OR o.status = :overdue')
            ->andWhere(\sprintf('NOT EXISTS (SELECT s.id FROM %s s WHERE s.hidden = true AND (s.product = o.product OR s.contractor = o.contractor))', OrderSettings::class))
            ->setParameter('statuses', [OrderStatus::Expected, OrderStatus::Approaching, OrderStatus::Overdue])
            ->setParameter('overdue', OrderStatus::Overdue)
            ->setParameter('until', $until)
            ->orderBy('o.predictedDate', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * @return list<OrderCycle> zamknięte cykle towaru albo dostawcy, od najnowszego
     */
    public function findHistory(Product|Contractor $subject, int $limit = 10): array
    {
        return $this->createQueryBuilder('o')
            ->where(\sprintf('o.%s = :subject', $subject instanceof Product ? 'product' : 'contractor'))
            ->andWhere('o.closedAt IS NOT NULL')
            ->setParameter('subject', $subject)
            ->orderBy('o.closedAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    public static function key(OrderCycle $cycle): string
    {
        return null !== $cycle->getProduct() ? 'p:'.$cycle->getProduct()->getId() : 'c:'.$cycle->getContractor()?->getId();
    }

    private function open(): QueryBuilder
    {
        return $this->createQueryBuilder('o')
            ->leftJoin('o.product', 'p')
            ->leftJoin('o.contractor', 'c')
            ->where('o.closedAt IS NULL')
            ->andWhere('p.id IS NULL OR p.active = true');
    }
}
