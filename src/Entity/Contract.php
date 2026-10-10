<?php

declare(strict_types=1);

namespace App\Entity;

use App\Enum\ContractKind;
use App\Enum\ContractStatus;
use App\Enum\PaymentFrequency;
use App\Farm\BelongsToFarm;
use App\Farm\FarmScoped;
use App\Repository\ContractRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

#[ORM\Entity(repositoryClass: ContractRepository::class)]
#[ORM\Index(columns: ['farm_id', 'status', 'valid_to'])]
class Contract implements \Stringable, FarmScoped
{
    use BelongsToFarm;

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

    /** Kwota jednej płatności dzierżawy (czynszu). */
    #[ORM\Column(type: Types::DECIMAL, precision: 14, scale: 2, nullable: true)]
    #[Assert\PositiveOrZero]
    private ?string $rentAmount = null;

    #[ORM\Column(length: 12, nullable: true, enumType: PaymentFrequency::class)]
    private ?PaymentFrequency $paymentFrequency = null;

    #[ORM\Column(type: Types::DATE_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $firstPaymentDate = null;

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

    public function getRentAmount(): ?string
    {
        return $this->rentAmount;
    }

    public function setRentAmount(?string $rentAmount): static
    {
        $this->rentAmount = $rentAmount;

        return $this;
    }

    public function getPaymentFrequency(): ?PaymentFrequency
    {
        return $this->paymentFrequency;
    }

    public function setPaymentFrequency(?PaymentFrequency $paymentFrequency): static
    {
        $this->paymentFrequency = $paymentFrequency;

        return $this;
    }

    public function getFirstPaymentDate(): ?\DateTimeImmutable
    {
        return $this->firstPaymentDate;
    }

    public function setFirstPaymentDate(?\DateTimeImmutable $firstPaymentDate): static
    {
        $this->firstPaymentDate = $firstPaymentDate;

        return $this;
    }

    public function isLease(): bool
    {
        return ContractKind::Lease === $this->kind;
    }

    /**
     * Najbliższy termin płatności dzierżawy (dziś lub później), w okresie obowiązywania umowy.
     */
    public function nextPaymentDate(?\DateTimeImmutable $today = null): ?\DateTimeImmutable
    {
        if (!$this->isLease() || !$this->isActive() || null === $this->firstPaymentDate || null === $this->paymentFrequency) {
            return null;
        }

        $today = ($today ?? new \DateTimeImmutable('today'))->setTime(0, 0);
        $step = $this->paymentFrequency->months();
        $day = (int) $this->firstPaymentDate->format('j');
        $first = $this->firstPaymentDate->setTime(0, 0);

        for ($i = 0; $i < 1200; $i += $step) {
            $month = $first->modify('first day of this month')->modify(\sprintf('+%d months', $i));
            $date = $month->setDate((int) $month->format('Y'), (int) $month->format('n'), min($day, (int) $month->format('t')));
            if (null !== $this->validTo && $date > $this->validTo) {
                return null;
            }
            if ($date >= $today) {
                return $date;
            }
        }

        return null;
    }

    /**
     * Suma czynszu w skali roku.
     */
    public function yearlyRent(): ?float
    {
        if (null === $this->rentAmount || null === $this->paymentFrequency) {
            return null;
        }

        return (float) $this->rentAmount * 12 / $this->paymentFrequency->months();
    }

    #[Assert\Callback]
    public function validateLease(ExecutionContextInterface $context): void
    {
        if (!$this->isLease()) {
            return;
        }
        if (null !== $this->rentAmount && null === $this->paymentFrequency) {
            $context->buildViolation('Podaj, jak często płacona jest dzierżawa.')->atPath('paymentFrequency')->addViolation();
        }
        if (null !== $this->paymentFrequency && null === $this->firstPaymentDate) {
            $context->buildViolation('Podaj termin pierwszej płatności – od niego liczą się kolejne.')->atPath('firstPaymentDate')->addViolation();
        }
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
