<?php

declare(strict_types=1);

namespace App\Entity;

use App\Farm\BelongsToFarm;
use App\Farm\FarmScoped;
use App\Repository\MachineServiceRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

/**
 * Wpis w historii serwisowej: przegląd, naprawa, wymiana oleju….
 */
#[ORM\Entity(repositoryClass: MachineServiceRepository::class)]
#[ORM\Index(columns: ['farm_id', 'date'])]
#[ORM\Index(columns: ['farm_id', 'next_service_date'])]
class MachineService implements FarmScoped
{
    use BelongsToFarm;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(type: Types::DATE_IMMUTABLE)]
    #[Assert\NotNull(message: 'Podaj datę.')]
    #[Assert\LessThanOrEqual('today', message: 'Wpis dotyczy wykonanej usługi – data nie może być z przyszłości. Przyszły termin wpisz w „Następny serwis”.')]
    private ?\DateTimeImmutable $date = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 1, nullable: true)]
    #[Assert\PositiveOrZero]
    private ?string $meterValue = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(onDelete: 'SET NULL')]
    #[Assert\NotNull(message: 'Wybierz rodzaj serwisu.')]
    private ?ServiceType $type = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $description = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(onDelete: 'SET NULL')]
    private ?Contractor $contractor = null;

    /** Wykonawca spoza listy kontrahentów, np. „we własnym zakresie”. */
    #[ORM\Column(length: 120, nullable: true)]
    #[Assert\Length(max: 120)]
    private ?string $performerName = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 14, scale: 2, nullable: true)]
    #[Assert\PositiveOrZero]
    private ?string $netCost = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(onDelete: 'SET NULL')]
    private ?Invoice $invoice = null;

    #[ORM\Column(type: Types::DATE_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $nextServiceDate = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 1, nullable: true)]
    #[Assert\PositiveOrZero]
    private ?string $nextServiceMeter = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(onDelete: 'SET NULL')]
    private ?User $createdBy = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $createdAt;

    /** @var Collection<int, MachineServicePart> */
    #[ORM\OneToMany(targetEntity: MachineServicePart::class, mappedBy: 'service', cascade: ['persist', 'remove'], orphanRemoval: true)]
    #[ORM\OrderBy(['id' => 'ASC'])]
    #[Assert\Valid]
    private Collection $parts;

    /** @var Collection<int, MachineServiceAttachment> */
    #[ORM\OneToMany(targetEntity: MachineServiceAttachment::class, mappedBy: 'service', cascade: ['persist', 'remove'], orphanRemoval: true)]
    #[ORM\OrderBy(['id' => 'ASC'])]
    private Collection $attachments;

    public function __construct(
        #[ORM\ManyToOne]
        #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
        private Machine $machine,
    ) {
        $this->parts = new ArrayCollection();
        $this->attachments = new ArrayCollection();
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getMachine(): Machine
    {
        return $this->machine;
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

    public function getMeterValue(): ?string
    {
        return $this->meterValue;
    }

    public function setMeterValue(?string $meterValue): static
    {
        $this->meterValue = $meterValue;

        return $this;
    }

    public function getType(): ?ServiceType
    {
        return $this->type;
    }

    public function setType(?ServiceType $type): static
    {
        $this->type = $type;

        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): static
    {
        $this->description = $description;

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

    public function getPerformerName(): ?string
    {
        return $this->performerName;
    }

    public function setPerformerName(?string $performerName): static
    {
        $this->performerName = $performerName;

        return $this;
    }

    public function getPerformerLabel(): ?string
    {
        return $this->contractor?->getDisplayName() ?? $this->performerName;
    }

    public function getNetCost(): ?string
    {
        return $this->netCost;
    }

    public function setNetCost(?string $netCost): static
    {
        $this->netCost = $netCost;

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

    public function getNextServiceDate(): ?\DateTimeImmutable
    {
        return $this->nextServiceDate;
    }

    public function setNextServiceDate(?\DateTimeImmutable $nextServiceDate): static
    {
        $this->nextServiceDate = $nextServiceDate;

        return $this;
    }

    public function getNextServiceMeter(): ?string
    {
        return $this->nextServiceMeter;
    }

    public function setNextServiceMeter(?string $nextServiceMeter): static
    {
        $this->nextServiceMeter = $nextServiceMeter;

        return $this;
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

    /**
     * @return Collection<int, MachineServicePart>
     */
    public function getParts(): Collection
    {
        return $this->parts;
    }

    public function addPart(MachineServicePart $part): static
    {
        if (!$this->parts->contains($part)) {
            $part->setService($this);
            $this->parts->add($part);
        }

        return $this;
    }

    public function removePart(MachineServicePart $part): static
    {
        $this->parts->removeElement($part);

        return $this;
    }

    /**
     * @return Collection<int, MachineServiceAttachment>
     */
    public function getAttachments(): Collection
    {
        return $this->attachments;
    }

    public function addAttachment(MachineServiceAttachment $attachment): static
    {
        if (!$this->attachments->contains($attachment)) {
            $this->attachments->add($attachment);
        }

        return $this;
    }

    public function removeAttachment(MachineServiceAttachment $attachment): static
    {
        $this->attachments->removeElement($attachment);

        return $this;
    }

    #[Assert\Callback]
    public function validate(ExecutionContextInterface $context): void
    {
        if (null !== $this->nextServiceDate && null !== $this->date && $this->nextServiceDate <= $this->date) {
            $context->buildViolation('Następny serwis musi być po dacie tego wpisu.')->atPath('nextServiceDate')->addViolation();
        }
        if (null !== $this->nextServiceMeter && null !== $this->meterValue && (float) $this->nextServiceMeter <= (float) $this->meterValue) {
            $context->buildViolation('Licznik następnego serwisu musi być wyższy niż obecny.')->atPath('nextServiceMeter')->addViolation();
        }
    }
}
