<?php

declare(strict_types=1);

namespace App\Analytics;

use App\Entity\Invoice;
use App\Entity\InvoiceItem;
use App\Entity\Machine;
use App\Entity\MachineService;
use App\Enum\InvoiceKind;
use App\Enum\InvoiceStatus;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\QueryBuilder;

/**
 * Koszty przypisane do ośrodków. Pozycja faktury z własnym ośrodkiem idzie do niego,
 * reszta faktury – do ośrodka faktury. Każda złotówka liczy się raz.
 * Do maszyn dochodzą wpisy serwisowe z kwotą wpisaną ręcznie (bez faktury).
 */
final class CostCenterCosts
{
    public const string FUEL = 'Paliwo';
    public const string PARTS = 'Części i materiały';
    public const string SERVICE = 'Serwis i usługi';

    public function __construct(
        private readonly EntityManagerInterface $em,
    ) {
    }

    /**
     * @return list<array{center: int, date: \DateTimeImmutable, net: float, category: ?string}>
     */
    public function entries(?\DateTimeImmutable $from = null, ?\DateTimeImmutable $to = null): array
    {
        $entries = [];

        $items = $this->range($this->em->createQueryBuilder()
            ->select('IDENTITY(it.costCenter) AS center', 'IDENTITY(it.invoice) AS invoice', 'i.issueDate AS date', 'it.netValue AS net', 'pc.name AS category')
            ->from(InvoiceItem::class, 'it')
            ->join('it.invoice', 'i')
            ->leftJoin('it.product', 'p')
            ->leftJoin('p.category', 'pc')
            ->where('it.costCenter IS NOT NULL')
            ->andWhere('it.beforeCorrection = false'), $from, $to)
            ->getQuery()
            ->getArrayResult();

        $assigned = [];
        foreach ($items as $row) {
            $entries[] = ['center' => (int) $row['center'], 'date' => $row['date'], 'net' => (float) $row['net'], 'category' => $row['category']];
            $assigned[$row['invoice']] = ($assigned[$row['invoice']] ?? 0.0) + (float) $row['net'];
        }

        $invoices = $this->range($this->em->createQueryBuilder()
            ->select('i.id', 'IDENTITY(i.costCenter) AS center', 'i.issueDate AS date', 'i.net', 'cc.name AS category')
            ->from(Invoice::class, 'i')
            ->leftJoin('i.costCategory', 'cc')
            ->where('i.costCenter IS NOT NULL'), $from, $to)
            ->getQuery()
            ->getArrayResult();

        foreach ($invoices as $row) {
            $net = round((float) $row['net'] - ($assigned[$row['id']] ?? 0.0), 2);
            if (0.0 !== $net) {
                $entries[] = ['center' => (int) $row['center'], 'date' => $row['date'], 'net' => $net, 'category' => $row['category']];
            }
        }

        $services = $this->em->createQueryBuilder()
            ->select('IDENTITY(m.costCenter) AS center', 's.date', 's.netCost AS net')
            ->from(MachineService::class, 's')
            ->join('s.machine', 'm')
            ->where('s.invoice IS NULL')
            ->andWhere('s.netCost IS NOT NULL')
            ->andWhere('m.costCenter IS NOT NULL');
        if (null !== $from) {
            $services->andWhere('s.date >= :from')->setParameter('from', $from->format('Y-m-d'));
        }
        if (null !== $to) {
            $services->andWhere('s.date <= :to')->setParameter('to', $to->format('Y-m-d'));
        }
        foreach ($services->getQuery()->getArrayResult() as $row) {
            $entries[] = ['center' => (int) $row['center'], 'date' => $row['date'], 'net' => (float) $row['net'], 'category' => self::SERVICE];
        }

        return $entries;
    }

    /**
     * @param list<array{center: int, net: float}> $entries
     *
     * @return array<int, float> ośrodek => suma netto
     */
    public static function totals(array $entries): array
    {
        $totals = [];
        foreach ($entries as $entry) {
            $totals[$entry['center']] = ($totals[$entry['center']] ?? 0.0) + $entry['net'];
        }

        return $totals;
    }

    /**
     * Podział kosztów maszyny wg nazwy kategorii towaru albo kosztu.
     */
    public static function machineGroup(?string $category): string
    {
        $category = mb_strtolower((string) $category);

        return match (true) {
            str_contains($category, 'paliw') => self::FUEL,
            str_contains($category, 'częś') || str_contains($category, 'filtr') || str_contains($category, 'olej') || str_contains($category, 'smar') => self::PARTS,
            default => self::SERVICE,
        };
    }

    public static function isFeed(?string $category): bool
    {
        $category = mb_strtolower((string) $category);

        return str_contains($category, 'pasz') || str_contains($category, 'mineraln');
    }

    /**
     * @return array<int, Machine> ośrodek => maszyna
     */
    public function machinesByCenter(): array
    {
        $machines = [];
        foreach ($this->em->getRepository(Machine::class)->findAll() as $machine) {
            if (null !== ($center = $machine->getCostCenter()?->getId())) {
                $machines[$center] = $machine;
            }
        }

        return $machines;
    }

    private function range(QueryBuilder $qb, ?\DateTimeImmutable $from, ?\DateTimeImmutable $to): QueryBuilder
    {
        $qb->andWhere('i.kind = :kind')
            ->andWhere('i.status != :skipped')
            ->setParameter('kind', InvoiceKind::Cost)
            ->setParameter('skipped', InvoiceStatus::Skipped);
        if (null !== $from) {
            $qb->andWhere('i.issueDate >= :from')->setParameter('from', $from->format('Y-m-d'));
        }
        if (null !== $to) {
            $qb->andWhere('i.issueDate <= :to')->setParameter('to', $to->format('Y-m-d'));
        }

        return $qb;
    }
}
