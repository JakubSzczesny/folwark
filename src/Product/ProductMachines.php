<?php

declare(strict_types=1);

namespace App\Product;

use App\Entity\InvoiceItem;
use App\Entity\Machine;
use App\Entity\MachineService;
use App\Entity\Product;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Maszyny, w których towar występuje: faktury z tym towarem podpięte pod serwis maszyny
 * albo przypisane (całe lub pozycją) do ośrodka kosztów maszyny.
 */
final class ProductMachines
{
    public function __construct(
        private readonly EntityManagerInterface $em,
    ) {
    }

    /**
     * @return list<array{machine: Machine, lastDate: \DateTimeImmutable, invoices: int}>
     */
    public function find(Product $product): array
    {
        $found = [];

        $services = $this->em->createQueryBuilder()
            ->select('s', 'm', 'i')
            ->from(MachineService::class, 's')
            ->join('s.machine', 'm')
            ->join('s.invoice', 'i')
            ->where('EXISTS (SELECT 1 FROM '.InvoiceItem::class.' it WHERE it.invoice = i AND it.product = :product)')
            ->setParameter('product', $product)
            ->getQuery()
            ->getResult();
        foreach ($services as $service) {
            $invoice = $service->getInvoice();
            if (null !== $invoice) {
                $this->add($found, $service->getMachine(), (int) $invoice->getId(), $invoice->getIssueDate());
            }
        }

        $rows = $this->em->createQueryBuilder()
            ->select('m AS machine', 'i.id AS invoiceId', 'i.issueDate AS date')
            ->from(InvoiceItem::class, 'it')
            ->join('it.invoice', 'i')
            ->join(Machine::class, 'm', 'WITH', 'm.costCenter IS NOT NULL AND (m.costCenter = it.costCenter OR (it.costCenter IS NULL AND m.costCenter = i.costCenter))')
            ->where('it.product = :product')
            ->setParameter('product', $product)
            ->getQuery()
            ->getResult();
        foreach ($rows as $row) {
            $this->add($found, $row['machine'], (int) $row['invoiceId'], $row['date']);
        }

        $result = [];
        foreach ($found as $entry) {
            $result[] = ['machine' => $entry['machine'], 'lastDate' => $entry['lastDate'], 'invoices' => \count($entry['invoices'])];
        }
        usort($result, static fn (array $a, array $b): int => $b['lastDate'] <=> $a['lastDate']);

        return $result;
    }

    /**
     * @param array<int, array{machine: Machine, lastDate: \DateTimeImmutable, invoices: array<int, true>}> $found
     */
    private function add(array &$found, Machine $machine, int $invoiceId, \DateTimeImmutable $date): void
    {
        $id = (int) $machine->getId();
        $found[$id] ??= ['machine' => $machine, 'lastDate' => $date, 'invoices' => []];
        $found[$id]['invoices'][$invoiceId] = true;
        if ($date > $found[$id]['lastDate']) {
            $found[$id]['lastDate'] = $date;
        }
    }
}
