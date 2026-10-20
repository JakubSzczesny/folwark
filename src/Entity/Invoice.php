<?php

declare(strict_types=1);

namespace App\Entity;

use App\Enum\InvoiceKind;
use App\Enum\InvoiceStatus;
use App\Enum\InvoiceType;
use App\Enum\PaymentStatus;
use App\Farm\BelongsToFarm;
use App\Farm\FarmScoped;
use App\Repository\InvoiceRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: InvoiceRepository::class)]
#[ORM\UniqueConstraint(columns: ['farm_id', 'ksef_number'])]
#[ORM\Index(columns: ['farm_id', 'issue_date'])]
#[ORM\Index(columns: ['farm_id', 'kind', 'status'])]
#[ORM\Index(columns: ['farm_id', 'kind', 'issue_date'])]
#[ORM\Index(columns: ['farm_id', 'contractor_nip'])]
#[ORM\Index(columns: ['farm_id', 'payment', 'payment_due'])]
#[ORM\Index(columns: ['corrected_ksef_number'])]
class Invoice implements \Stringable, FarmScoped
{
    use BelongsToFarm;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 36)]
    private string $ksefNumber;

    #[ORM\Column(length: 256)]
    private string $number;

    #[ORM\Column(length: 10, enumType: InvoiceKind::class)]
    private InvoiceKind $kind;

    #[ORM\Column]
    private bool $kindOverridden = false;

    #[ORM\Column(length: 12, enumType: InvoiceType::class)]
    private InvoiceType $type = InvoiceType::Invoice;

    #[ORM\Column(length: 12)]
    private string $ksefInvoiceType = 'Vat';

    #[ORM\Column(length: 10)]
    private string $formCode = 'FA';

    #[ORM\Column(type: Types::DATE_IMMUTABLE)]
    private \DateTimeImmutable $issueDate;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $ksefReceivedAt;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $permanentStorageAt;

    #[ORM\Column(type: Types::DATE_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $paymentDue = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(onDelete: 'SET NULL')]
    private ?Contractor $contractor = null;

    #[ORM\Column(length: 50, nullable: true)]
    private ?string $contractorNip = null;

    #[ORM\Column(length: 512, nullable: true)]
    private ?string $contractorName = null;

    #[ORM\Column(length: 10)]
    private string $sellerNip = '';

    #[ORM\Column(length: 512, nullable: true)]
    private ?string $sellerName = null;

    #[ORM\Column(length: 10)]
    private string $buyerIdentifierType = 'Nip';

    #[ORM\Column(length: 50, nullable: true)]
    private ?string $buyerIdentifier = null;

    #[ORM\Column(length: 512, nullable: true)]
    private ?string $buyerName = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 14, scale: 2)]
    private string $net = '0.00';

    #[ORM\Column(type: Types::DECIMAL, precision: 14, scale: 2)]
    private string $vat = '0.00';

    #[ORM\Column(type: Types::DECIMAL, precision: 14, scale: 2)]
    private string $gross = '0.00';

    #[ORM\Column(length: 3)]
    private string $currency = 'PLN';

    #[ORM\Column(length: 16, enumType: InvoiceStatus::class)]
    private InvoiceStatus $status = InvoiceStatus::New;

    #[ORM\Column(length: 8, enumType: PaymentStatus::class)]
    private PaymentStatus $payment = PaymentStatus::Unknown;

    #[ORM\Column(type: Types::DATE_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $paidAt = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(onDelete: 'SET NULL')]
    private ?Contract $contract = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(onDelete: 'SET NULL')]
    private ?CostCenter $costCenter = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(onDelete: 'SET NULL')]
    private ?CostCategory $costCategory = null;

    #[ORM\ManyToOne(targetEntity: self::class)]
    #[ORM\JoinColumn(onDelete: 'SET NULL')]
    private ?Invoice $correctedInvoice = null;

    #[ORM\Column(length: 36, nullable: true)]
    private ?string $correctedKsefNumber = null;

    #[ORM\Column(length: 256, nullable: true)]
    private ?string $correctedNumber = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $xmlPath = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $xmlFetchedAt = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(onDelete: 'SET NULL')]
    private ?User $readBy = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $readAt = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $notes = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $createdAt;

    /** @var Collection<int, InvoiceItem> */
    #[ORM\OneToMany(targetEntity: InvoiceItem::class, mappedBy: 'invoice', cascade: ['persist', 'remove'], orphanRemoval: true)]
    #[ORM\OrderBy(['position' => 'ASC'])]
    private Collection $items;

    public function __construct(string $ksefNumber, string $number, InvoiceKind $kind, \DateTimeImmutable $issueDate)
    {
        $this->ksefNumber = $ksefNumber;
        $this->number = $number;
        $this->kind = $kind;
        $this->issueDate = $issueDate;
        $this->ksefReceivedAt = new \DateTimeImmutable();
        $this->permanentStorageAt = new \DateTimeImmutable();
        $this->createdAt = new \DateTimeImmutable();
        $this->items = new ArrayCollection();
        $this->payment = InvoiceKind::Cost === $kind ? PaymentStatus::Unpaid : PaymentStatus::Unknown;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getKsefNumber(): string
    {
        return $this->ksefNumber;
    }

    public function getNumber(): string
    {
        return $this->number;
    }

    public function setNumber(string $number): static
    {
        $this->number = $number;

        return $this;
    }

    public function getKind(): InvoiceKind
    {
        return $this->kind;
    }

    public function setKind(InvoiceKind $kind): static
    {
        $this->kind = $kind;

        return $this;
    }

    public function isCost(): bool
    {
        return InvoiceKind::Cost === $this->kind;
    }

    /**
     * Czy pozycje tej faktury przypisuje się do towarów: zakupowa, nie „tylko koszt” i nie pominięta.
     */
    public function expectsGoods(): bool
    {
        return $this->isCost() && !\in_array($this->status, InvoiceStatus::withoutGoods(), true);
    }

    /**
     * Pozycje bez towaru z kartoteki (i nie oznaczone „pomiń”) – faktura wymaga jeszcze przypisania.
     */
    public function countUnassignedItems(): int
    {
        if (!$this->expectsGoods()) {
            return 0;
        }

        return \count($this->items->filter(static fn (InvoiceItem $item): bool => $item->isUnassigned()));
    }

    public function isKindOverridden(): bool
    {
        return $this->kindOverridden;
    }

    public function setKindOverridden(bool $kindOverridden): static
    {
        $this->kindOverridden = $kindOverridden;

        return $this;
    }

    public function getType(): InvoiceType
    {
        return $this->type;
    }

    public function setType(InvoiceType $type): static
    {
        $this->type = $type;

        return $this;
    }

    public function isCorrection(): bool
    {
        return InvoiceType::Correction === $this->type;
    }

    public function getKsefInvoiceType(): string
    {
        return $this->ksefInvoiceType;
    }

    public function setKsefInvoiceType(string $ksefInvoiceType): static
    {
        $this->ksefInvoiceType = $ksefInvoiceType;

        return $this;
    }

    public function getFormCode(): string
    {
        return $this->formCode;
    }

    public function setFormCode(string $formCode): static
    {
        $this->formCode = $formCode;

        return $this;
    }

    public function getIssueDate(): \DateTimeImmutable
    {
        return $this->issueDate;
    }

    public function setIssueDate(\DateTimeImmutable $issueDate): static
    {
        $this->issueDate = $issueDate;

        return $this;
    }

    public function getKsefReceivedAt(): \DateTimeImmutable
    {
        return $this->ksefReceivedAt;
    }

    public function setKsefReceivedAt(\DateTimeImmutable $ksefReceivedAt): static
    {
        $this->ksefReceivedAt = $ksefReceivedAt;

        return $this;
    }

    public function getPermanentStorageAt(): \DateTimeImmutable
    {
        return $this->permanentStorageAt;
    }

    public function setPermanentStorageAt(\DateTimeImmutable $permanentStorageAt): static
    {
        $this->permanentStorageAt = $permanentStorageAt;

        return $this;
    }

    public function getPaymentDue(): ?\DateTimeImmutable
    {
        return $this->paymentDue;
    }

    public function setPaymentDue(?\DateTimeImmutable $paymentDue): static
    {
        $this->paymentDue = $paymentDue;

        return $this;
    }

    public function getContractor(): ?Contractor
    {
        return $this->contractor;
    }

    public function setContractor(?Contractor $contractor): static
    {
        $this->contractor = $contractor;

        return $this;
    }

    public function getContractorNip(): ?string
    {
        return $this->contractorNip;
    }

    public function setContractorNip(?string $contractorNip): static
    {
        $this->contractorNip = $contractorNip;

        return $this;
    }

    public function getContractorName(): ?string
    {
        return $this->contractorName;
    }

    public function setContractorName(?string $contractorName): static
    {
        $this->contractorName = $contractorName;

        return $this;
    }

    public function getContractorLabel(): string
    {
        return $this->contractor?->getDisplayName() ?? $this->contractorName ?? $this->contractorNip ?? '—';
    }

    public function getSellerNip(): string
    {
        return $this->sellerNip;
    }

    public function getSellerName(): ?string
    {
        return $this->sellerName;
    }

    public function setSeller(string $nip, ?string $name): static
    {
        $this->sellerNip = $nip;
        $this->sellerName = $name;

        return $this;
    }

    public function getBuyerIdentifierType(): string
    {
        return $this->buyerIdentifierType;
    }

    public function getBuyerIdentifier(): ?string
    {
        return $this->buyerIdentifier;
    }

    public function getBuyerName(): ?string
    {
        return $this->buyerName;
    }

    public function setBuyer(string $identifierType, ?string $identifier, ?string $name): static
    {
        $this->buyerIdentifierType = $identifierType;
        $this->buyerIdentifier = $identifier;
        $this->buyerName = $name;

        return $this;
    }

    public function getNet(): string
    {
        return $this->net;
    }

    public function getVat(): string
    {
        return $this->vat;
    }

    public function getGross(): string
    {
        return $this->gross;
    }

    public function setAmounts(string $net, string $vat, string $gross, string $currency = 'PLN'): static
    {
        $this->net = $net;
        $this->vat = $vat;
        $this->gross = $gross;
        $this->currency = strtoupper($currency);

        return $this;
    }

    public function getCurrency(): string
    {
        return $this->currency;
    }

    public function getStatus(): InvoiceStatus
    {
        return $this->status;
    }

    public function setStatus(InvoiceStatus $status): static
    {
        $this->status = $status;

        return $this;
    }

    public function isNew(): bool
    {
        return InvoiceStatus::New === $this->status && null === $this->readAt;
    }

    public function isUnread(): bool
    {
        return null === $this->readAt;
    }

    public function getPayment(): PaymentStatus
    {
        return $this->payment;
    }

    public function setPayment(PaymentStatus $payment): static
    {
        $this->payment = $payment;

        return $this;
    }

    public function getPaidAt(): ?\DateTimeImmutable
    {
        return $this->paidAt;
    }

    public function markPaid(?\DateTimeImmutable $paidAt = null): static
    {
        $this->payment = PaymentStatus::Paid;
        $this->paidAt = $paidAt ?? new \DateTimeImmutable('today');

        return $this;
    }

    public function isPaid(): bool
    {
        return PaymentStatus::Paid === $this->payment;
    }

    public function getContract(): ?Contract
    {
        return $this->contract;
    }

    public function setContract(?Contract $contract): static
    {
        $this->contract = $contract;

        return $this;
    }

    public function getCostCenter(): ?CostCenter
    {
        return $this->costCenter;
    }

    public function setCostCenter(?CostCenter $costCenter): static
    {
        $this->costCenter = $costCenter;

        return $this;
    }

    public function getCostCategory(): ?CostCategory
    {
        return $this->costCategory;
    }

    public function setCostCategory(?CostCategory $costCategory): static
    {
        $this->costCategory = $costCategory;

        return $this;
    }

    public function getCorrectedInvoice(): ?self
    {
        return $this->correctedInvoice;
    }

    public function setCorrectedInvoice(?self $correctedInvoice): static
    {
        $this->correctedInvoice = $correctedInvoice;

        return $this;
    }

    public function getCorrectedKsefNumber(): ?string
    {
        return $this->correctedKsefNumber;
    }

    public function getCorrectedNumber(): ?string
    {
        return $this->correctedNumber;
    }

    public function setCorrected(?string $ksefNumber, ?string $number): static
    {
        $this->correctedKsefNumber = $ksefNumber;
        $this->correctedNumber = $number;

        return $this;
    }

    public function getXmlPath(): ?string
    {
        return $this->xmlPath;
    }

    public function hasXml(): bool
    {
        return null !== $this->xmlPath;
    }

    public function getXmlFetchedAt(): ?\DateTimeImmutable
    {
        return $this->xmlFetchedAt;
    }

    public function setXml(string $path): static
    {
        $this->xmlPath = $path;
        $this->xmlFetchedAt = new \DateTimeImmutable();

        return $this;
    }

    public function getReadBy(): ?User
    {
        return $this->readBy;
    }

    public function getReadAt(): ?\DateTimeImmutable
    {
        return $this->readAt;
    }

    public function markRead(?User $user): bool
    {
        if (null !== $this->readAt) {
            return false;
        }

        $this->readAt = new \DateTimeImmutable();
        $this->readBy = $user;
        if (InvoiceStatus::New === $this->status) {
            $this->status = InvoiceStatus::Read;
        }

        return true;
    }

    public function getNotes(): ?string
    {
        return $this->notes;
    }

    public function setNotes(?string $notes): static
    {
        $this->notes = $notes;

        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    /**
     * @return Collection<int, InvoiceItem>
     */
    public function getItems(): Collection
    {
        return $this->items;
    }

    public function addItem(InvoiceItem $item): static
    {
        if (!$this->items->contains($item)) {
            $this->items->add($item);
        }

        return $this;
    }

    public function clearItems(): static
    {
        $this->items->clear();

        return $this;
    }

    public function __toString(): string
    {
        return $this->number;
    }
}
