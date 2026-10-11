<?php

declare(strict_types=1);

namespace App\Machine;

use App\Entity\Invoice;
use App\Entity\InvoiceItem;
use App\Entity\Machine;
use App\Enum\InvoiceKind;
use App\Repository\MachineServiceRepository;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Koszty maszyny: faktury i pozycje faktur przypisane do jej ośrodka kosztów
 * oraz wpisy serwisowe (z kwotą albo podpiętą fakturą). Każda faktura liczy się raz.
 */
final class MachineCosts
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly MachineServiceRepository $services,
    ) {
    }

    /**
     * @return list<array{date: \DateTimeImmutable, source: string, description: string, net: float, invoice: ?Invoice}>
     */
    public function rows(Machine $machine): array
    {
        $rows = [];
        $counted = [];
        $center = $machine->getCostCenter();

        if (null !== $center) {
            /** @var list<Invoice> $invoices */
            $invoices = $this->em->createQueryBuilder()
                ->select('i')
                ->from(Invoice::class, 'i')
                ->where('i.costCenter = :center')
                ->andWhere('i.kind = :kind')
                ->setParameter('center', $center)
                ->setParameter('kind', InvoiceKind::Cost)
                ->getQuery()
                ->getResult();
            foreach ($invoices as $invoice) {
                $counted[$invoice->getId()] = true;
                $rows[] = ['date' => $invoice->getIssueDate(), 'source' => 'Faktura', 'description' => $invoice->getContractorLabel(), 'net' => (float) $invoice->getNet(), 'invoice' => $invoice];
            }

            /** @var list<InvoiceItem> $items */
            $items = $this->em->createQueryBuilder()
                ->select('it', 'i')
                ->from(InvoiceItem::class, 'it')
                ->join('it.invoice', 'i')
                ->where('it.costCenter = :center')
                ->andWhere('it.skipped = false')
                ->andWhere('i.kind = :kind')
                ->setParameter('center', $center)
                ->setParameter('kind', InvoiceKind::Cost)
                ->getQuery()
                ->getResult();
            foreach ($items as $item) {
                $invoice = $item->getInvoice();
                if (isset($counted[$invoice->getId()])) {
                    continue;
                }
                $rows[] = ['date' => $invoice->getIssueDate(), 'source' => 'Pozycja faktury', 'description' => $item->getName(), 'net' => (float) $item->getNetValue(), 'invoice' => $invoice];
            }
        }

        foreach ($this->services->findForMachine($machine) as $service) {
            $invoice = $service->getInvoice();
            if (null !== $invoice && isset($counted[$invoice->getId()])) {
                continue;
            }
            $net = $service->getNetCost() ?? $invoice?->getNet();
            if (null === $net || null === $service->getDate()) {
                continue;
            }
            if (null !== $invoice) {
                $counted[$invoice->getId()] = true;
            }
            $rows[] = [
                'date' => $service->getDate(),
                'source' => 'Serwis',
                'description' => trim(($service->getType()?->getName() ?? 'Serwis').' · '.($service->getPerformerLabel() ?? ''), ' ·'),
                'net' => (float) $net,
                'invoice' => $invoice,
            ];
        }

        usort($rows, static fn (array $a, array $b): int => $b['date'] <=> $a['date']);

        return $rows;
    }

    /**
     * @param list<array{date: \DateTimeImmutable, net: float}> $rows
     *
     * @return array<int, float> rok => suma netto, od najnowszego
     */
    public static function yearlyTotals(array $rows): array
    {
        $totals = [];
        foreach ($rows as $row) {
            $year = (int) $row['date']->format('Y');
            $totals[$year] = ($totals[$year] ?? 0.0) + $row['net'];
        }
        krsort($totals);

        return $totals;
    }
}
