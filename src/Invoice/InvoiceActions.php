<?php

declare(strict_types=1);

namespace App\Invoice;

use App\Audit\AuditLogger;
use App\Contractor\ContractorResolver;
use App\Entity\Contract;
use App\Entity\CostCategory;
use App\Entity\CostCenter;
use App\Entity\Invoice;
use App\Entity\User;
use App\Enum\InvoiceKind;
use App\Enum\InvoiceStatus;
use App\Enum\NotificationType;
use App\Enum\PaymentStatus;
use App\Repository\InvoiceRepository;
use App\Repository\NotificationRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * Zmiany statusów i powiązań faktury. Każda zmiana trafia do dziennika zdarzeń.
 */
class InvoiceActions
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly AuditLogger $audit,
        private readonly ContractorResolver $contractors,
        private readonly NotificationRepository $notifications,
        private readonly InvoiceRepository $invoices,
        private readonly UrlGeneratorInterface $urls,
    ) {
    }

    /**
     * Odczytanie faktury gasi powiadomienie, które do niej prowadzi, a gdy nowych faktur już nie ma –
     * także zbiorcze „N nowych faktur”.
     */
    public function markRead(Invoice $invoice, ?User $user): void
    {
        if (!$invoice->markRead($user)) {
            return;
        }

        $this->audit->log('Invoice', $invoice->getId(), 'read', [], $user);
        $this->em->flush();

        if (null !== $user) {
            $this->notifications->markReadByType($user, NotificationType::NewInvoices, $this->urls->generate('app_invoice_show', ['id' => $invoice->getId()]));
            if (0 === $this->invoices->countNew()) {
                $this->notifications->markReadByType($user, NotificationType::NewInvoices);
            }
        }
    }

    public function skip(Invoice $invoice): void
    {
        $this->changeStatus($invoice, InvoiceStatus::Skipped);
        $this->em->flush();
    }

    public function restore(Invoice $invoice): void
    {
        $this->changeStatus($invoice, InvoiceStatus::Read);
        $this->em->flush();
    }

    public function costOnly(Invoice $invoice, ?CostCenter $center, ?CostCategory $category): void
    {
        $invoice->setCostCenter($center)->setCostCategory($category);
        $this->changeStatus($invoice, InvoiceStatus::CostOnly, [
            'costCenter' => $center?->getName(),
            'costCategory' => $category?->getName(),
        ]);
        $this->em->flush();
    }

    public function markPaid(Invoice $invoice, ?\DateTimeImmutable $date = null): void
    {
        if ($invoice->isPaid()) {
            return;
        }

        $invoice->markPaid($date);
        $this->audit->log('Invoice', $invoice->getId(), 'paid', ['paidAt' => $invoice->getPaidAt()?->format('Y-m-d')]);
        $this->em->flush();
    }

    public function markUnpaid(Invoice $invoice): void
    {
        $invoice->setPayment(PaymentStatus::Unpaid);
        $this->audit->log('Invoice', $invoice->getId(), 'unpaid');
        $this->em->flush();
    }

    public function assignContract(Invoice $invoice, ?Contract $contract): void
    {
        $old = $invoice->getContract();
        if ($old === $contract) {
            return;
        }

        $invoice->setContract($contract);
        $this->audit->log('Invoice', $invoice->getId(), 'contract', [
            'old' => $old?->getNumber(),
            'new' => $contract?->getNumber(),
        ]);
        $this->em->flush();
    }

    public function changeKind(Invoice $invoice, InvoiceKind $kind): void
    {
        if ($invoice->getKind() === $kind) {
            return;
        }

        $old = $invoice->getKind();
        $invoice->setKind($kind)->setKindOverridden(true);

        $isCost = InvoiceKind::Cost === $kind;
        $nip = $isCost ? $invoice->getSellerNip() : ('Nip' === $invoice->getBuyerIdentifierType() ? $invoice->getBuyerIdentifier() : null);
        $name = $isCost ? $invoice->getSellerName() : $invoice->getBuyerName();
        $invoice->setContractorNip($isCost ? $invoice->getSellerNip() : $invoice->getBuyerIdentifier())->setContractorName($name);
        $invoice->setContractor($this->contractors->resolve($invoice, $nip, $name));

        if (!$isCost && PaymentStatus::Unpaid === $invoice->getPayment()) {
            $invoice->setPayment(PaymentStatus::Unknown);
        }

        $this->audit->log('Invoice', $invoice->getId(), 'kind', ['old' => $old->value, 'new' => $kind->value]);
        $this->em->flush();
    }

    public function saveNotes(Invoice $invoice, ?string $notes): void
    {
        $invoice->setNotes($notes);
        $this->audit->log('Invoice', $invoice->getId(), 'notes');
        $this->em->flush();
    }

    /**
     * @param array<string, mixed> $details
     */
    public function changeStatus(Invoice $invoice, InvoiceStatus $status, array $details = []): void
    {
        $old = $invoice->getStatus();
        if ($old === $status && [] === $details) {
            return;
        }

        $invoice->setStatus($status);
        $this->audit->log('Invoice', $invoice->getId(), 'status', ['old' => $old->value, 'new' => $status->value] + array_filter($details));
    }
}
