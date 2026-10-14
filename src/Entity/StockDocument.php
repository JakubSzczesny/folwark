<?php

declare(strict_types=1);

namespace App\Entity;

use App\Enum\DocumentStatus;
use App\Enum\DocumentType;
use App\Farm\BelongsToFarm;
use App\Farm\FarmScoped;
use App\Repository\StockDocumentRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

/**
 * Dokument magazynowy. Numer dostaje przy zatwierdzeniu, wtedy też powstają ruchy magazynowe.
 */
#[ORM\Entity(repositoryClass: StockDocumentRepository::class)]
#[ORM\Index(columns: ['farm_id', 'date'])]
#[ORM\Index(columns: ['farm_id', 'type', 'date'])]
#[ORM\UniqueConstraint(columns: ['farm_id', 'number'])]
class StockDocument implements \Stringable, FarmScoped
{
    use BelongsToFarm;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 3, enumType: DocumentType::class)]
    private DocumentType $type;

    #[ORM\Column(length: 40, nullable: true)]
    private ?string $number = null;

    #[ORM\Column(type: Types::SMALLINT, nullable: true)]
    private ?int $sequence = null;

    #[ORM\Column(type: Types::DATE_IMMUTABLE)]
    #[Assert\NotNull(message: 'Podaj datę.')]
    private ?\DateTimeImmutable $date;

    #[ORM\Column(length: 10, enumType: DocumentStatus::class)]
    private DocumentStatus $status = DocumentStatus::Draft;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(onDelete: 'SET NULL')]
    private ?Contractor $contractor = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(onDelete: 'SET NULL')]
    private ?Contract $contract = null;

    #[ORM\ManyToOne]
    private ?Warehouse $sourceWarehouse = null;

    #[ORM\ManyToOne]
    private ?Warehouse $targetWarehouse = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(onDelete: 'SET NULL')]
    private ?CostCenter $costCenter = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(onDelete: 'SET NULL')]
    private ?Invoice $invoice = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $notes = null;

    /** @var Collection<int, StockDocumentItem> */
    #[ORM\OneToMany(targetEntity: StockDocumentItem::class, mappedBy: 'document', cascade: ['persist', 'remove'], orphanRemoval: true)]
    #[ORM\OrderBy(['id' => 'ASC'])]
    #[Assert\Valid]
    private Collection $items;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(onDelete: 'SET NULL')]
    private ?User $createdBy = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $createdAt;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(onDelete: 'SET NULL')]
    private ?User $approvedBy = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $approvedAt = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $cancelledAt = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $cancelReason = null;

    /** Dokument, który ten zastąpił przy „Popraw”. */
    #[ORM\ManyToOne]
    #[ORM\JoinColumn(onDelete: 'SET NULL')]
    private ?StockDocument $replaces = null;

    public function __construct(DocumentType $type, ?\DateTimeImmutable $date = null)
    {
        $this->type = $type;
        $this->date = $date ?? new \DateTimeImmutable('today');
        $this->items = new ArrayCollection();
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getType(): DocumentType
    {
        return $this->type;
    }

    public function getNumber(): ?string
    {
        return $this->number;
    }

    public function getSequence(): ?int
    {
        return $this->sequence;
    }

    public function setNumber(string $number, int $sequence): static
    {
        $this->number = $number;
        $this->sequence = $sequence;

        return $this;
    }

    public function getLabel(): string
    {
        return $this->number ?? $this->type->value.' (szkic)';
    }

    public function getDate(): ?\DateTimeImmutable
    {
        return $this->date;
    }

    public function setDate(?\DateTimeImmutable $date): static
    {
        $this->date = $date;

        return $this;
    }

    public function getStatus(): DocumentStatus
    {
        return $this->status;
    }

    public function isDraft(): bool
    {
        return DocumentStatus::Draft === $this->status;
    }

    public function isApproved(): bool
    {
        return DocumentStatus::Approved === $this->status;
    }

    public function isCancelled(): bool
    {
        return DocumentStatus::Cancelled === $this->status;
    }

    public function approve(?User $user): static
    {
        $this->status = DocumentStatus::Approved;
        $this->approvedBy = $user;
        $this->approvedAt = new \DateTimeImmutable();

        return $this;
    }

    public function cancel(string $reason): static
    {
        $this->status = DocumentStatus::Cancelled;
        $this->cancelledAt = new \DateTimeImmutable();
        $this->cancelReason = $reason;

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

    public function getContract(): ?Contract
    {
        return $this->contract;
    }

    public function setContract(?Contract $contract): static
    {
        $this->contract = $contract;

        return $this;
    }

    public function getSourceWarehouse(): ?Warehouse
    {
        return $this->sourceWarehouse;
    }

    public function setSourceWarehouse(?Warehouse $sourceWarehouse): static
    {
        $this->sourceWarehouse = $sourceWarehouse;

        return $this;
    }

    public function getTargetWarehouse(): ?Warehouse
    {
        return $this->targetWarehouse;
    }

    public function setTargetWarehouse(?Warehouse $targetWarehouse): static
    {
        $this->targetWarehouse = $targetWarehouse;

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

    public function getInvoice(): ?Invoice
    {
        return $this->invoice;
    }

    public function setInvoice(?Invoice $invoice): static
    {
        $this->invoice = $invoice;

        return $this;
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

    /**
     * @return Collection<int, StockDocumentItem>
     */
    public function getItems(): Collection
    {
        return $this->items;
    }

    public function addItem(StockDocumentItem $item): static
    {
        if (!$this->items->contains($item)) {
            $item->setDocument($this);
            $this->items->add($item);
        }

        return $this;
    }

    public function removeItem(StockDocumentItem $item): static
    {
        $this->items->removeElement($item);

        return $this;
    }

    public function getNetTotal(): ?string
    {
        $total = null;
        foreach ($this->items as $item) {
            if (null !== $item->getNetValue()) {
                $total = ($total ?? 0.0) + (float) $item->getNetValue();
            }
        }

        return null !== $total ? number_format($total, 2, '.', '') : null;
    }

    public function getCreatedBy(): ?User
    {
        return $this->createdBy;
    }

    public function setCreatedBy(?User $createdBy): static
    {
        $this->createdBy = $createdBy;

        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getApprovedBy(): ?User
    {
        return $this->approvedBy;
    }

    public function getApprovedAt(): ?\DateTimeImmutable
    {
        return $this->approvedAt;
    }

    public function getCancelledAt(): ?\DateTimeImmutable
    {
        return $this->cancelledAt;
    }

    public function getCancelReason(): ?string
    {
        return $this->cancelReason;
    }

    public function getReplaces(): ?StockDocument
    {
        return $this->replaces;
    }

    public function setReplaces(?StockDocument $replaces): static
    {
        $this->replaces = $replaces;

        return $this;
    }

    /**
     * Kopia jako nowy szkic – do poprawienia zatwierdzonego dokumentu.
     */
    public function copyAsDraft(): self
    {
        $copy = new self($this->type, $this->date);
        $copy->contractor = $this->contractor;
        $copy->contract = $this->contract;
        $copy->sourceWarehouse = $this->sourceWarehouse;
        $copy->targetWarehouse = $this->targetWarehouse;
        $copy->costCenter = $this->costCenter;
        $copy->invoice = $this->invoice;
        $copy->notes = $this->notes;
        $copy->replaces = $this;
        foreach ($this->items as $item) {
            $copy->addItem($item->copy());
        }

        return $copy;
    }

    #[Assert\Callback]
    public function validateWarehouses(ExecutionContextInterface $context): void
    {
        if ($this->type->needsSource() && null === $this->sourceWarehouse) {
            $context->buildViolation('Wybierz magazyn, z którego wydajesz.')->atPath('sourceWarehouse')->addViolation();
        }
        if ($this->type->needsTarget() && null === $this->targetWarehouse) {
            $context->buildViolation('Wybierz magazyn.')->atPath('targetWarehouse')->addViolation();
        }
        if (null !== $this->sourceWarehouse && $this->sourceWarehouse === $this->targetWarehouse) {
            $context->buildViolation('Magazyn docelowy musi być inny niż źródłowy.')->atPath('targetWarehouse')->addViolation();
        }
        if ($this->items->isEmpty()) {
            $context->buildViolation('Dodaj co najmniej jedną pozycję.')->atPath('items')->addViolation();
        }
    }

    public function __toString(): string
    {
        return $this->getLabel();
    }
}
