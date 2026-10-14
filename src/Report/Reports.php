<?php

declare(strict_types=1);

namespace App\Report;

use App\Entity\Invoice;
use App\Entity\InvoiceItem;
use App\Entity\Machine;
use App\Entity\Product;
use App\Enum\InvoiceKind;
use App\Enum\InvoiceStatus;
use App\Repository\MachineServiceRepository;
use App\Repository\OrderCycleRepository;
use App\Twig\FormatExtension;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Zestawienia ze specyfikacji 15: faktury za okres, olej napędowy do zwrotu akcyzy,
 * zakupy per towar i dostawca, terminy maszyn, przewidywane zamówienia.
 */
final class Reports
{
    /** Pozycje oleju napędowego rozpoznawane po nazwie, gdy nie są przypisane do towaru. */
    public const string DIESEL_PATTERN = '/olej\s+nap[eę]dowy|\bON\b|diesel/iu';

    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly OrderCycleRepository $cycles,
        private readonly MachineServiceRepository $services,
    ) {
    }

    public function invoices(\DateTimeImmutable $from, \DateTimeImmutable $to, ?InvoiceKind $kind): Report
    {
        $qb = $this->em->createQueryBuilder()
            ->select('i', 'c', 'cc', 'cat')
            ->from(Invoice::class, 'i')
            ->leftJoin('i.contractor', 'c')
            ->leftJoin('i.costCenter', 'cc')
            ->leftJoin('i.costCategory', 'cat')
            ->where('i.issueDate BETWEEN :from AND :to')
            ->setParameter('from', $from->format('Y-m-d'))
            ->setParameter('to', $to->format('Y-m-d'))
            ->orderBy('i.issueDate')
            ->addOrderBy('i.number');
        if (null !== $kind) {
            $qb->andWhere('i.kind = :kind')->setParameter('kind', $kind);
        }

        $rows = [];
        $totals = [0.0, 0.0, 0.0];
        foreach ($qb->getQuery()->getResult() as $invoice) {
            \assert($invoice instanceof Invoice);
            $rows[] = [
                $invoice->getIssueDate(),
                $invoice->getNumber(),
                $invoice->getKind()->label(),
                $invoice->getContractorLabel(),
                $invoice->getContractorNip(),
                (float) $invoice->getNet(),
                (float) $invoice->getVat(),
                (float) $invoice->getGross(),
                $invoice->getCurrency(),
                $invoice->getPaymentDue(),
                $invoice->getStatus()->label(),
                $invoice->getPayment()->label(),
                $invoice->getCostCenter()?->getName(),
                $invoice->getCostCategory()?->getName(),
            ];
            if ('PLN' === $invoice->getCurrency() && InvoiceStatus::Skipped !== $invoice->getStatus()) {
                $totals[0] += (float) $invoice->getNet();
                $totals[1] += (float) $invoice->getVat();
                $totals[2] += (float) $invoice->getGross();
            }
        }

        $kindLabel = match ($kind) {
            InvoiceKind::Cost => 'kosztowe',
            InvoiceKind::Income => 'przychodowe',
            default => 'wszystkie',
        };

        return new Report(
            'Faktury '.$kindLabel,
            self::period($from, $to),
            \sprintf('faktury-%s-%s-%s', $kindLabel, $from->format('Y-m-d'), $to->format('Y-m-d')),
            [
                ['label' => 'Data wystawienia', 'type' => 'date'],
                ['label' => 'Numer', 'width' => 22],
                ['label' => 'Rodzaj', 'width' => 12],
                ['label' => 'Kontrahent', 'width' => 34],
                ['label' => 'NIP', 'width' => 13],
                ['label' => 'Netto', 'type' => 'money'],
                ['label' => 'VAT', 'type' => 'money'],
                ['label' => 'Brutto', 'type' => 'money'],
                ['label' => 'Waluta', 'width' => 7],
                ['label' => 'Termin płatności', 'type' => 'date'],
                ['label' => 'Status', 'width' => 18],
                ['label' => 'Płatność', 'width' => 12],
                ['label' => 'Ośrodek kosztów', 'width' => 22],
                ['label' => 'Kategoria kosztu', 'width' => 18],
            ],
            $rows,
            ['Razem PLN', null, null, null, null, round($totals[0], 2), round($totals[1], 2), round($totals[2], 2), null, null, null, null, null, null],
            ['Sumy obejmują faktury w PLN bez pominiętych.'],
        );
    }

    /**
     * Okresy wniosku o zwrot akcyzy: luty–lipiec (wniosek w sierpniu) i sierpień–styczeń (wniosek w lutym).
     *
     * @return array{\DateTimeImmutable, \DateTimeImmutable}
     */
    public static function dieselPeriod(int $year, int $half): array
    {
        return 1 === $half
            ? [new \DateTimeImmutable(\sprintf('%d-08-01', $year - 1)), new \DateTimeImmutable(\sprintf('%d-01-31', $year))]
            : [new \DateTimeImmutable(\sprintf('%d-02-01', $year)), new \DateTimeImmutable(\sprintf('%d-07-31', $year))];
    }

    /**
     * Olej napędowy z faktur kosztowych: litry i kwoty per faktura. Pozycje przypisane do towaru
     * „olej napędowy” albo – nieprzypisane – rozpoznane po nazwie.
     */
    public function diesel(\DateTimeImmutable $from, \DateTimeImmutable $to): Report
    {
        $products = [];
        foreach ($this->em->getRepository(Product::class)->findAll() as $product) {
            if (1 === preg_match(self::DIESEL_PATTERN, $product->getName())) {
                $products[(int) $product->getId()] = true;
            }
        }

        /** @var list<InvoiceItem> $items */
        $items = $this->em->createQueryBuilder()
            ->select('it', 'i', 'c')
            ->from(InvoiceItem::class, 'it')
            ->join('it.invoice', 'i')
            ->leftJoin('i.contractor', 'c')
            ->where('i.kind = :kind')
            ->andWhere('i.status != :skipped')
            ->andWhere('it.beforeCorrection = false')
            ->andWhere('i.issueDate BETWEEN :from AND :to')
            ->setParameter('kind', InvoiceKind::Cost)
            ->setParameter('skipped', InvoiceStatus::Skipped)
            ->setParameter('from', $from->format('Y-m-d'))
            ->setParameter('to', $to->format('Y-m-d'))
            ->orderBy('i.issueDate')
            ->addOrderBy('i.number')
            ->getQuery()
            ->getResult();

        $sums = [];
        $itemCounts = [];
        $unknownUnits = 0;
        foreach ($items as $item) {
            $invoice = $item->getInvoice();
            $id = (int) $invoice->getId();
            $itemCounts[$id] = ($itemCounts[$id] ?? 0) + 1;
            $product = $item->getProduct();
            $isDiesel = null !== $product ? isset($products[(int) $product->getId()]) : 1 === preg_match(self::DIESEL_PATTERN, $item->getName());
            if (!$isDiesel) {
                continue;
            }

            $liters = self::liters($item);
            if (null === $liters) {
                ++$unknownUnits;
            }
            $sums[$id] ??= ['invoice' => $invoice, 'items' => 0, 'liters' => 0.0, 'net' => 0.0, 'vat' => 0.0];
            ++$sums[$id]['items'];
            $sums[$id]['liters'] += $liters ?? 0.0;
            $sums[$id]['net'] += (float) $item->getNetValue();
            $sums[$id]['vat'] += self::itemVat($item);
        }

        $rows = [];
        $totals = ['liters' => 0.0, 'net' => 0.0, 'vat' => 0.0, 'gross' => 0.0];
        foreach ($sums as $id => $sum) {
            $invoice = $sum['invoice'];
            $whole = $sum['items'] === $itemCounts[$id];
            $liters = round($sum['liters'], 3);
            $net = $whole ? (float) $invoice->getNet() : round($sum['net'], 2);
            $vat = $whole ? (float) $invoice->getVat() : round($sum['vat'], 2);
            $gross = $whole ? (float) $invoice->getGross() : round($sum['net'] + $sum['vat'], 2);
            $rows[] = [$invoice->getIssueDate(), $invoice->getNumber(), $invoice->getContractorLabel(), $invoice->getContractorNip(), $liters, $net, $vat, $gross, $invoice->getKsefNumber()];
            $totals['liters'] += $liters;
            $totals['net'] += $net;
            $totals['vat'] += $vat;
            $totals['gross'] += $gross;
        }

        $notes = ['Pozycje przypisane do towaru z „olej napędowy” (albo „ON”, „diesel”) w nazwie oraz nieprzypisane pozycje o takiej nazwie. Gdy cała faktura to olej napędowy, kwoty są z faktury. Bez faktur pominiętych.'];
        $withoutItems = (int) $this->em->createQueryBuilder()
            ->select('COUNT(i.id)')
            ->from(Invoice::class, 'i')
            ->where('i.kind = :kind')
            ->andWhere('i.status != :skipped')
            ->andWhere('i.issueDate BETWEEN :from AND :to')
            ->andWhere('NOT EXISTS (SELECT 1 FROM '.InvoiceItem::class.' x WHERE x.invoice = i)')
            ->setParameter('kind', InvoiceKind::Cost)
            ->setParameter('skipped', InvoiceStatus::Skipped)
            ->setParameter('from', $from->format('Y-m-d'))
            ->setParameter('to', $to->format('Y-m-d'))
            ->getQuery()
            ->getSingleScalarResult();
        if ($withoutItems > 0) {
            $notes[] = \sprintf('Uwaga: %d %s z okresu bez pobranych pozycji (XML) – nie są uwzględnione. Pobierz pozycje na fakturze.', $withoutItems, FormatExtension::plural($withoutItems, 'faktura kosztowa', 'faktury kosztowe', 'faktur kosztowych'));
        }
        if ($unknownUnits > 0) {
            $notes[] = \sprintf('Uwaga: %d %s bez ilości w litrach – sprawdź przelicznik jednostek.', $unknownUnits, FormatExtension::plural($unknownUnits, 'pozycja', 'pozycje', 'pozycji'));
        }

        return new Report(
            'Olej napędowy – zwrot akcyzy',
            self::period($from, $to).' · faktur: '.\count($rows),
            \sprintf('olej-napedowy-%s-%s', $from->format('Y-m'), $to->format('Y-m')),
            [
                ['label' => 'Data wystawienia', 'type' => 'date'],
                ['label' => 'Numer faktury', 'width' => 24],
                ['label' => 'Sprzedawca', 'width' => 34],
                ['label' => 'NIP', 'width' => 13],
                ['label' => 'Litry', 'type' => 'qty'],
                ['label' => 'Netto', 'type' => 'money'],
                ['label' => 'VAT', 'type' => 'money'],
                ['label' => 'Brutto', 'type' => 'money'],
                ['label' => 'Numer KSeF', 'width' => 40],
            ],
            $rows,
            ['Razem', null, null, null, round($totals['liters'], 3), round($totals['net'], 2), round($totals['vat'], 2), round($totals['gross'], 2), null],
            $notes,
        );
    }

    public function purchases(\DateTimeImmutable $from, \DateTimeImmutable $to): Report
    {
        $result = $this->em->createQueryBuilder()
            ->select('p.name AS product', 'u.symbol AS unit', 'COALESCE(c.name, i.contractorName) AS supplier', 'COUNT(DISTINCT i.id) AS invoices', 'SUM(it.quantityInProductUnit) AS quantity', 'SUM(it.netValue) AS net')
            ->from(InvoiceItem::class, 'it')
            ->join('it.invoice', 'i')
            ->join('it.product', 'p')
            ->leftJoin('p.baseUnit', 'u')
            ->leftJoin('i.contractor', 'c')
            ->where('i.kind = :kind')
            ->andWhere('i.status != :skipped')
            ->andWhere('it.beforeCorrection = false')
            ->andWhere('i.issueDate BETWEEN :from AND :to')
            ->setParameter('kind', InvoiceKind::Cost)
            ->setParameter('skipped', InvoiceStatus::Skipped)
            ->setParameter('from', $from->format('Y-m-d'))
            ->setParameter('to', $to->format('Y-m-d'))
            ->groupBy('product', 'unit', 'supplier')
            ->orderBy('product')
            ->addOrderBy('net', 'DESC')
            ->getQuery()
            ->getArrayResult();

        $rows = [];
        $total = 0.0;
        foreach ($result as $row) {
            $quantity = (float) $row['quantity'];
            $net = round((float) $row['net'], 2);
            $total += $net;
            $rows[] = [$row['product'], $row['supplier'], (int) $row['invoices'], round($quantity, 3), $row['unit'], $net, $quantity > 0 ? round($net / $quantity, 4) : null];
        }

        return new Report(
            'Zakupy per towar i dostawca',
            self::period($from, $to),
            \sprintf('zakupy-%s-%s', $from->format('Y-m-d'), $to->format('Y-m-d')),
            [
                ['label' => 'Towar', 'width' => 32],
                ['label' => 'Dostawca', 'width' => 32],
                ['label' => 'Faktur', 'type' => 'int'],
                ['label' => 'Ilość', 'type' => 'qty'],
                ['label' => 'Jm.', 'width' => 6],
                ['label' => 'Netto', 'type' => 'money'],
                ['label' => 'Śr. cena netto', 'type' => 'money'],
            ],
            $rows,
            ['Razem', null, null, null, null, round($total, 2), null],
            ['Pozycje faktur kosztowych przypisane do towarów („Przypisz towary”).'],
        );
    }

    public function machineDeadlines(\DateTimeImmutable $today): Report
    {
        $plans = [];
        foreach ($this->services->findOpenPlans() as $plan) {
            $plans[(int) $plan->getMachine()->getId()][] = $plan;
        }

        $rows = [];
        foreach ($this->em->getRepository(Machine::class)->findBy(['active' => true], ['name' => 'ASC']) as $machine) {
            $machinePlans = $plans[(int) $machine->getId()] ?? [null];
            foreach ($machinePlans as $plan) {
                $rows[] = [
                    $machine->getLabel(),
                    $machine->getRegistrationNumber(),
                    $machine->getInsuranceUntil(),
                    $machine->getInspectionUntil(),
                    $plan?->getType()?->getName() ?? (null !== $plan ? 'Serwis' : null),
                    $plan?->getNextServiceDate(),
                    null !== $plan?->getNextServiceMeter() ? (float) $plan->getNextServiceMeter() : null,
                    null !== $machine->getMeterValue() ? (float) $machine->getMeterValue() : null,
                    $machine->getMeterUnit()?->label(),
                ];
            }
        }

        return new Report(
            'Terminy maszyn',
            'Stan na '.FormatExtension::date($today),
            'terminy-maszyn-'.$today->format('Y-m-d'),
            [
                ['label' => 'Maszyna', 'width' => 32],
                ['label' => 'Nr rej.', 'width' => 12],
                ['label' => 'Ubezpieczenie do', 'type' => 'date'],
                ['label' => 'Przegląd do', 'type' => 'date'],
                ['label' => 'Serwis', 'width' => 20],
                ['label' => 'Serwis – data', 'type' => 'date'],
                ['label' => 'Serwis – licznik', 'type' => 'qty'],
                ['label' => 'Licznik obecnie', 'type' => 'qty'],
                ['label' => 'Jedn. licznika', 'width' => 10],
            ],
            $rows,
        );
    }

    public function orders(\DateTimeImmutable $today, int $days): Report
    {
        $rows = [];
        $total = 0.0;
        foreach ($this->cycles->findDue($today->modify(\sprintf('+%d days', $days))) as $cycle) {
            $amount = null !== $cycle->getTypicalAmount() ? (float) $cycle->getTypicalAmount() : null;
            $total += $amount ?? 0.0;
            $rows[] = [
                $cycle->getLabel(),
                $cycle->getPredictedDate(),
                $cycle->getReminderDate(),
                null !== $cycle->getTypicalQuantity() ? (float) $cycle->getTypicalQuantity() : null,
                $cycle->getProduct()?->getUnitSymbol(),
                $amount,
                $cycle->getStatus()->label(),
            ];
        }

        return new Report(
            \sprintf('Przewidywane zamówienia – %d dni', $days),
            self::period($today, $today->modify(\sprintf('+%d days', $days))).' · także zaległe',
            \sprintf('zamowienia-%d-dni-%s', $days, $today->format('Y-m-d')),
            [
                ['label' => 'Towar / dostawca', 'width' => 36],
                ['label' => 'Przewidywana data', 'type' => 'date'],
                ['label' => 'Przypomnienie', 'type' => 'date'],
                ['label' => 'Typowa ilość', 'type' => 'qty'],
                ['label' => 'Jm.', 'width' => 6],
                ['label' => 'Typowa kwota netto', 'type' => 'money'],
                ['label' => 'Status', 'width' => 16],
            ],
            $rows,
            ['Razem', null, null, null, null, round($total, 2), null],
        );
    }

    public static function period(\DateTimeImmutable $from, \DateTimeImmutable $to): string
    {
        return FormatExtension::date($from).' – '.FormatExtension::date($to);
    }

    private static function liters(InvoiceItem $item): ?float
    {
        if (null !== $item->getQuantityInProductUnit() && 0 === strcasecmp((string) $item->getProduct()?->getUnitSymbol(), 'l')) {
            return (float) $item->getQuantityInProductUnit();
        }
        $unit = mb_strtolower(trim((string) $item->getUnit(), ' .'));

        return match (true) {
            null === $item->getQuantity() => null,
            \in_array($unit, ['l', 'litr', 'litry', 'litrów', 'ltr', 'dm3'], true) => (float) $item->getQuantity(),
            \in_array($unit, ['m3', 'm³'], true) => (float) $item->getQuantity() * 1000,
            default => null,
        };
    }

    private static function itemVat(InvoiceItem $item): float
    {
        $rate = $item->getVatRate();

        return null !== $rate && is_numeric($rate) ? round((float) $item->getNetValue() * (float) $rate / 100, 2) : 0.0;
    }
}
