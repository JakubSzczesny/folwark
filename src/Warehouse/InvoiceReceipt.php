<?php

declare(strict_types=1);

namespace App\Warehouse;

use App\Entity\Invoice;
use App\Entity\StockDocument;
use App\Entity\StockDocumentItem;
use App\Entity\User;
use App\Entity\Warehouse;
use App\Enum\DocumentType;
use App\Repository\StockDocumentRepository;
use Doctrine\ORM\EntityManagerInterface;

/**
 * „Utwórz też PZ” – przyjęcie z pozycji faktury przypisanych do towarów, od razu zatwierdzone.
 */
final class InvoiceReceipt
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly StockDocumentRepository $documents,
        private readonly StockLedger $ledger,
    ) {
    }

    public function existing(Invoice $invoice): ?StockDocument
    {
        foreach ($this->documents->findForInvoice($invoice) as $document) {
            if (DocumentType::Pz === $document->getType() && !$document->isCancelled()) {
                return $document;
            }
        }

        return null;
    }

    /**
     * @return array{?StockDocument, list<string>} dokument (null, gdy nie było czego przyjąć) i ostrzeżenia
     */
    public function create(Invoice $invoice, Warehouse $warehouse, ?User $user): array
    {
        if (null !== ($existing = $this->existing($invoice))) {
            return [$existing, []];
        }

        $document = new StockDocument(DocumentType::Pz, $invoice->getIssueDate());
        $document->setInvoice($invoice)
            ->setContractor($invoice->getContractor())
            ->setTargetWarehouse($warehouse)
            ->setCostCenter($invoice->getCostCenter())
            ->setCreatedBy($user)
            ->setNotes('Z faktury '.$invoice->getNumber());

        foreach ($invoice->getItems() as $invoiceItem) {
            $quantity = (float) $invoiceItem->getQuantityInProductUnit();
            if (null === $invoiceItem->getProduct() || $invoiceItem->isSkipped() || $invoiceItem->isBeforeCorrection() || $quantity <= 0) {
                continue;
            }
            $item = (new StockDocumentItem())
                ->setProduct($invoiceItem->getProduct())
                ->setEnteredQuantity(number_format($quantity, 4, '.', ''));
            if (null !== $invoiceItem->getNetValue()) {
                $item->setUnitPriceNet(number_format((float) $invoiceItem->getNetValue() / $quantity, 4, '.', ''));
            }
            $document->addItem($item->recalculate());
        }

        if ($document->getItems()->isEmpty()) {
            return [null, []];
        }

        $this->em->persist($document);

        return [$document, $this->ledger->approve($document, $user)];
    }
}
