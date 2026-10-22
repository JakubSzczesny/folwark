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
    public const string BULK_READ = 'przeczytane';
    public const string BULK_PAID = 'oplacone';
    public const string BULK_UNPAID = 'nieoplacone';
    public const string BULK_SKIP = 'pomin';
    public const string BULK_RESTORE = 'przywroc';
    public const string BULK_COST_ONLY = 'tylko-koszt';

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

    /**
     * @return int liczba odczytanych faktur
     */
    public function markAllRead(?User $user): int
    {
        $count = 0;
        foreach ($this->invoices->findBy(['readAt' => null]) as $invoice) {
            if ($invoice->markRead($user)) {
                $this->audit->log('Invoice', $invoice->getId(), 'read', [], $user);
                ++$count;
            }
        }
        $this->em->flush();

        if (null !== $user) {
            $this->notifications->markReadByType($user, NotificationType::NewInvoices);
        }

        return $count;
    }

    /**
     * Zmiana wielu faktur naraz (masowa edycja z listy). Faktury, których akcja nie dotyczy
     * (np. pominięcie faktury sprzedażowej, opłacenie już opłaconej), zostają bez zmian.
     *
     * @param iterable<Invoice> $invoices
     *
     * @return int liczba zmienionych faktur
     */
    public function bulk(iterable $invoices, string $action, ?User $user = null, ?CostCenter $center = null, ?CostCategory $category = null): int
    {
        $changed = [];
        foreach ($invoices as $invoice) {
            $done = match ($action) {
                self::BULK_READ => $this->bulkRead($invoice, $user),
                self::BULK_PAID => !$invoice->isPaid() && $this->bulkPaid($invoice),
                self::BULK_UNPAID => $invoice->isPaid() && $this->bulkUnpaid($invoice),
                self::BULK_SKIP => $invoice->isCost() && InvoiceStatus::Skipped !== $invoice->getStatus() && $this->bulkStatus($invoice, InvoiceStatus::Skipped),
                self::BULK_RESTORE => InvoiceStatus::Skipped === $invoice->getStatus() && $this->bulkStatus($invoice, InvoiceStatus::Read),
                self::BULK_COST_ONLY => $invoice->isCost() && $this->bulkCostOnly($invoice, $center, $category),
                default => throw new \InvalidArgumentException(\sprintf('Nieznana akcja: %s', $action)),
            };
            if ($done) {
                $changed[] = $invoice;
            }
        }
        $this->em->flush();

        if (self::BULK_READ === $action && null !== $user && [] !== $changed) {
            foreach ($changed as $invoice) {
                $this->notifications->markReadByType($user, NotificationType::NewInvoices, $this->urls->generate('app_invoice_show', ['id' => $invoice->getId()]));
            }
            if (0 === $this->invoices->countNew()) {
                $this->notifications->markReadByType($user, NotificationType::NewInvoices);
            }
        }

        return \count($changed);
    }

    private function bulkRead(Invoice $invoice, ?User $user): bool
    {
        if (!$invoice->markRead($user)) {
            return false;
        }
        $this->audit->log('Invoice', $invoice->getId(), 'read', [], $user);

        return true;
    }

    private function bulkPaid(Invoice $invoice): bool
    {
        $invoice->markPaid();
        $this->audit->log('Invoice', $invoice->getId(), 'paid', ['paidAt' => $invoice->getPaidAt()?->format('Y-m-d')]);

        return true;
    }

    private function bulkUnpaid(Invoice $invoice): bool
    {
        $invoice->setPayment(PaymentStatus::Unpaid);
        $this->audit->log('Invoice', $invoice->getId(), 'unpaid');

        return true;
    }

    private function bulkStatus(Invoice $invoice, InvoiceStatus $status): bool
    {
        $this->changeStatus($invoice, $status);

        return true;
    }

    private function bulkCostOnly(Invoice $invoice, ?CostCenter $center, ?CostCategory $category): bool
    {
        $invoice->setCostCenter($center)->setCostCategory($category);
        $this->changeStatus($invoice, InvoiceStatus::CostOnly, [
            'costCenter' => $center?->getName(),
            'costCategory' => $category?->getName(),
        ]);

        return true;
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
