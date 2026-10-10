<?php

declare(strict_types=1);

namespace App\Invoice;

use App\Contractor\ContractorResolver;
use App\Entity\Invoice;
use App\Enum\ContractKind;
use App\Enum\InvoiceStatus;
use App\Enum\InvoiceType;
use App\Ksef\Dto\InvoiceMetadata;
use App\Ksef\KsefIdentity;
use App\Ksef\SubjectType;
use App\Repository\ContractRepository;
use App\Repository\InvoiceRepository;
use App\Repository\InvoiceRuleRepository;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Tworzy fakturę z metadanych KSeF: klasyfikacja, kontrahent po NIP, reguły automatyczne, podpowiedź umowy.
 */
class InvoiceImporter
{
    /** @var array<string, true> numery KSeF zaimportowane w bieżącym przebiegu */
    private array $seen = [];

    public function __construct(
        private readonly InvoiceRepository $invoices,
        private readonly InvoiceClassifier $classifier,
        private readonly ContractorResolver $contractors,
        private readonly InvoiceRuleRepository $rules,
        private readonly ContractRepository $contracts,
        private readonly KsefIdentity $identity,
        private readonly EntityManagerInterface $em,
    ) {
    }

    public function import(InvoiceMetadata $metadata, SubjectType $queriedAs): ?Invoice
    {
        if (isset($this->seen[$metadata->ksefNumber]) || $this->invoices->existsByKsefNumber($metadata->ksefNumber)) {
            return null;
        }
        $this->seen[$metadata->ksefNumber] = true;

        $kind = $this->classifier->classify($metadata, $this->identity->farmNip(), $queriedAs);
        $counterparty = $this->classifier->counterparty($metadata, $kind);

        $invoice = (new Invoice($metadata->ksefNumber, $metadata->invoiceNumber, $kind, $metadata->issueDate))
            ->setType(InvoiceType::fromKsef($metadata->invoiceType))
            ->setKsefInvoiceType($metadata->invoiceType)
            ->setFormCode($metadata->formCode)
            ->setKsefReceivedAt($metadata->acquisitionDate)
            ->setPermanentStorageAt($metadata->permanentStorageDate)
            ->setSeller($metadata->sellerNip, $metadata->sellerName)
            ->setBuyer($metadata->buyerIdentifierType, $metadata->buyerIdentifier, $metadata->buyerName)
            ->setAmounts($metadata->netAmount, $metadata->vatAmount, $metadata->grossAmount, $metadata->currency)
            ->setContractorNip($counterparty['identifier'])
            ->setContractorName($counterparty['name']);
        $this->em->persist($invoice);

        $contractor = $this->contractors->resolve($invoice, $counterparty['nip'], $counterparty['name']);
        $invoice->setContractor($contractor);

        if ($invoice->isCost() && null !== $counterparty['nip']) {
            $rule = $this->rules->findActiveForNip($counterparty['nip']);
            if (null !== $rule) {
                $invoice->setStatus(InvoiceStatus::CostOnly)
                    ->setCostCenter($rule->getCostCenter())
                    ->setCostCategory($rule->getCostCategory());
            }
        }

        if (null !== $contractor && null !== $contractor->getId()) {
            $active = $this->contracts->findActiveFor($contractor, ContractKind::forInvoice($kind), $invoice->getIssueDate());
            if (1 === \count($active)) {
                $invoice->setContract($active[0]);
            }
        }

        return $invoice;
    }

    public function reset(): void
    {
        $this->seen = [];
        $this->contractors->reset();
    }
}
