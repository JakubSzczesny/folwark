<?php

declare(strict_types=1);

namespace App\Invoice;

use App\Contractor\ContractorResolver;
use App\Entity\Invoice;
use App\Entity\InvoiceItem;
use App\Invoice\Event\InvoiceXmlLoaded;
use App\Ksef\KsefClient;
use App\Ksef\Xml\InvoiceXmlParser;
use App\Ksef\Xml\ParsedInvoice;
use App\Repository\InvoiceRepository;
use App\Storage\FileStorage;
use Psr\EventDispatcher\EventDispatcherInterface;

/**
 * Pełny XML faktury: pobranie z KSeF, zapis lokalny i przeniesienie danych (pozycje, termin płatności,
 * powiązanie korekty, adres i rachunki kontrahenta).
 */
class InvoiceXmlService
{
    /** @var array<string, ParsedInvoice> */
    private array $parsed = [];

    public function __construct(
        private readonly KsefClient $ksef,
        private readonly InvoiceXmlParser $parser,
        private readonly FileStorage $storage,
        private readonly ContractorResolver $contractors,
        private readonly InvoiceRepository $invoices,
        private readonly EventDispatcherInterface $events,
    ) {
    }

    public function fetch(Invoice $invoice, bool $duringSync = false): ParsedInvoice
    {
        if ($invoice->hasXml() && $this->storage->exists((string) $invoice->getXmlPath())) {
            return $this->get($invoice) ?? throw new \LogicException('Brak XML faktury.');
        }

        $xml = $this->ksef->downloadInvoice($invoice->getKsefNumber());
        $parsed = $this->parser->parse($xml);

        $farm = $invoice->getFarm() ?? throw new \LogicException('Faktura bez gospodarstwa.');
        $path = \sprintf('%s/ksef/%s/%s.xml', FileStorage::farmDirectory($farm), $invoice->getIssueDate()->format('Y/m'), $invoice->getKsefNumber());
        $this->storage->write($path, $xml);
        $invoice->setXml($path);
        $this->parsed[$invoice->getKsefNumber()] = $parsed;

        $this->apply($invoice, $parsed);
        $this->events->dispatch(new InvoiceXmlLoaded($invoice, $parsed, $duringSync));

        return $parsed;
    }

    public function get(Invoice $invoice): ?ParsedInvoice
    {
        $path = $invoice->getXmlPath();
        if (null === $path) {
            return null;
        }

        // Plik mógł zniknąć (np. baza odtworzona z kopii bez plików) – pobieramy go z KSeF jeszcze raz.
        if (!isset($this->parsed[$invoice->getKsefNumber()]) && !$this->storage->exists($path)) {
            return $this->fetch($invoice);
        }

        return $this->parsed[$invoice->getKsefNumber()] ??= $this->parser->parse($this->storage->read($path));
    }

    public function raw(Invoice $invoice): ?string
    {
        $path = $invoice->getXmlPath();

        return null !== $path && $this->storage->exists($path) ? $this->storage->read($path) : null;
    }

    private function apply(Invoice $invoice, ParsedInvoice $parsed): void
    {
        if ($invoice->getItems()->isEmpty()) {
            foreach ($parsed->items as $row) {
                $item = (new InvoiceItem($invoice, $row->position, $row->name))
                    ->setValues($row->quantity, $row->unit, $row->unitPriceNet, $row->netValue, $row->vatRate)
                    ->setBeforeCorrection($row->beforeCorrection);
                $invoice->addItem($item);
            }
        }

        $invoice->setPaymentDue($parsed->paymentDue);
        if ($parsed->paid && !$invoice->isPaid()) {
            $invoice->markPaid($parsed->paidAt ?? $parsed->issueDate);
        }

        $corrected = $parsed->correctedInvoices[0] ?? null;
        if (null !== $corrected) {
            $invoice->setCorrected($corrected['ksefNumber'], $corrected['number']);
            $invoice->setCorrectedInvoice($this->invoices->findCorrected($corrected['ksefNumber'], $corrected['number'], $invoice->getContractorNip()));
        }

        foreach ($this->invoices->findCorrectionsOf($invoice) as $correction) {
            $correction->setCorrectedInvoice($invoice);
        }

        $contractor = $invoice->getContractor();
        if (null !== $contractor) {
            $party = $invoice->isCost() ? $parsed->seller : $parsed->buyer;
            $this->contractors->applyXml($contractor, $invoice, $party, $invoice->isCost(), $parsed->bankAccounts);
        }
    }
}
