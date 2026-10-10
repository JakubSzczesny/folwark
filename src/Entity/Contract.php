<?php

declare(strict_types=1);

namespace App\Entity;

use App\Enum\ContractKind;
use App\Enum\ContractStatus;
use App\Repository\ContractRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: ContractRepository::class)]
#[ORM\Index(columns: ['status', 'valid_to'])]
class Contract implements \Stringable
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    #[Assert\NotNull(message: 'Wybierz kontrahenta.')]
    private ?Contractor $contractor = null;

    #[ORM\Column(length: 100)]
    #[Assert\NotBlank]
    private string $number = '';

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank]
    private string $subject = '';

    #[ORM\Column(length: 10, enumType: ContractKind::class)]
    private ContractKind $kind = ContractKind::Sale;

    #[ORM\Column(type: Types::DATE_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $concludedAt = null;

    #[ORM\Column(type: Types::DATE_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $validFrom = null;

    #[ORM\Column(type: Types::DATE_IMMUTABLE, nullable: true)]
    #[Assert\GreaterThanOrEqual(propertyPath: 'validFrom', message: 'Koniec obowiązywania nie może być przed początkiem.')]
    private ?\DateTimeImmutable $validTo = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 15, scale: 3, nullable: true)]
    #[Assert\PositiveOrZero]
    private ?string $contractedQuantity = null;

    #[ORM\ManyToOne]
    private ?Unit $quantityUnit = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 15, scale: 4, nullable: true)]
    #[Assert\PositiveOrZero]
    private ?string $price = null;

    #[ORM\ManyToOne]
    private ?Unit $priceUnit = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $qualityTerms = null;

    #[ORM\Column(length: 12, enumType: ContractStatus::class)]
    private ContractStatus $status = ContractStatus::Active;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $notes = null;

    /** @var Collection<int, ContractAttachment> */
    #[ORM\OneToMany(targetEntity: ContractAttachment::class, mappedBy: 'contract', cascade: ['persist', 'remove'], orphanRemoval: true)]
    #[ORM\OrderBy(['addedAt' => 'ASC'])]
    private Collection $attachments;

    public function __construct()
    {
        $this->attachments = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
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

    public function getNumber(): string
    {
        return $this->number;
    }

    public function setNumber(string $number): static
    {
        $this->number = $number;

        return $this;
    }

    public function getSubject(): string
    {
        return $this->subject;
    }

    public function setSubject(string $subject): static
    {
        $this->subject = $subject;

        return $this;
    }

    public function getKind(): ContractKind
    {
        return $this->kind;
    }

    public function setKind(ContractKind $kind): static
    {
        $this->kind = $kind;

        return $this;
    }

    public function getConcludedAt(): ?\DateTimeImmutable
    {
        return $this->concludedAt;
    }

    public function setConcludedAt(?\DateTimeImmutable $concludedAt): static
    {
        $this->concludedAt = $concludedAt;

        return $this;
    }

    public function getValidFrom(): ?\DateTimeImmutable
    {
        return $this->validFrom;
    }

    public function setValidFrom(?\DateTimeImmutable $validFrom): static
    {
        $this->validFrom = $validFrom;

        return $this;
    }

    public function getValidTo(): ?\DateTimeImmutable
    {
        return $this->validTo;
    }

    public function setValidTo(?\DateTimeImmutable $validTo): static
    {
        $this->validTo = $validTo;

        return $this;
    }

    public function isIndefinite(): bool
    {
        return null === $this->validTo;
    }

    public function getContractedQuantity(): ?string
    {
        return $this->contractedQuantity;
    }

    public function setContractedQuantity(?string $contractedQuantity): static
    {
        $this->contractedQuantity = $contractedQuantity;

        return $this;
    }

    public function getQuantityUnit(): ?Unit
    {
        return $this->quantityUnit;
    }

    public function setQuantityUnit(?Unit $quantityUnit): static
    {
        $this->quantityUnit = $quantityUnit;

        return $this;
    }

    public function getPrice(): ?string
    {
        return $this->price;
    }

    public function setPrice(?string $price): static
    {
        $this->price = $price;

        return $this;
    }

    public function getPriceUnit(): ?Unit
    {
        return $this->priceUnit;
    }

    public function setPriceUnit(?Unit $priceUnit): static
    {
        $this->priceUnit = $priceUnit;

        return $this;
    }

    public function getQualityTerms(): ?string
    {
        return $this->qualityTerms;
    }

    public function setQualityTerms(?string $qualityTerms): static
    {
        $this->qualityTerms = $qualityTerms;

        return $this;
    }

    public function getStatus(): ContractStatus
    {
        return $this->status;
    }

    public function setStatus(ContractStatus $status): static
    {
        $this->status = $status;

        return $this;
    }

    public function isActive(): bool
    {
        return ContractStatus::Active === $this->status;
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
     * @return Collection<int, ContractAttachment>
     */
    public function getAttachments(): Collection
    {
        return $this->attachments;
    }

    public function addAttachment(ContractAttachment $attachment): static
    {
        if (!$this->attachments->contains($attachment)) {
            $this->attachments->add($attachment);
        }

        return $this;
    }

    public function removeAttachment(ContractAttachment $attachment): static
    {
        $this->attachments->removeElement($attachment);

        return $this;
    }

    public function getLabel(): string
    {
        return $this->number.' – '.$this->subject;
    }

    public function __toString(): string
    {
        return $this->getLabel();
    }
}
