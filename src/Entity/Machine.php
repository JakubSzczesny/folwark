<?php

declare(strict_types=1);

namespace App\Entity;

use App\Enum\MeterUnit;
use App\Enum\TareSource;
use App\Farm\BelongsToFarm;
use App\Farm\FarmScoped;
use App\Repository\MachineRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

#[ORM\Entity(repositoryClass: MachineRepository::class)]
#[ORM\Index(columns: ['farm_id', 'active', 'name'])]
#[ORM\Index(columns: ['farm_id', 'insurance_until'])]
#[ORM\Index(columns: ['farm_id', 'inspection_until'])]
#[ORM\Index(columns: ['farm_id', 'registration_number'])]
class Machine implements \Stringable, FarmScoped
{
    use BelongsToFarm;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 120)]
    #[Assert\NotBlank]
    #[Assert\Length(max: 120)]
    private string $name = '';

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(onDelete: 'SET NULL')]
    private ?MachineCategory $category = null;

    #[ORM\Column(length: 20, nullable: true)]
    #[Assert\Length(max: 20)]
    private ?string $registrationNumber = null;

    #[ORM\Column(length: 60, nullable: true)]
    #[Assert\Length(max: 60)]
    private ?string $brand = null;

    #[ORM\Column(length: 80, nullable: true)]
    #[Assert\Length(max: 80)]
    private ?string $model = null;

    #[ORM\Column(type: Types::SMALLINT, nullable: true)]
    #[Assert\Range(min: 1900, max: 2100)]
    private ?int $productionYear = null;

    /** Numer seryjny albo VIN. */
    #[ORM\Column(length: 40, nullable: true)]
    #[Assert\Length(max: 40)]
    private ?string $serialNumber = null;

    #[ORM\Column]
    private bool $owned = true;

    /** Właściciel pojazdu obcego (np. przewoźnika, który przyjeżdża na wagę). */
    #[ORM\ManyToOne]
    #[ORM\JoinColumn(onDelete: 'SET NULL')]
    private ?Contractor $contractor = null;

    #[ORM\Column(nullable: true)]
    private ?int $tareKg = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $tareSetAt = null;

    #[ORM\Column(length: 10, nullable: true, enumType: TareSource::class)]
    private ?TareSource $tareSource = null;

    #[ORM\Column(nullable: true)]
    #[Assert\PositiveOrZero]
    private ?int $payloadKg = null;

    #[ORM\Column(type: Types::DATE_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $insuranceUntil = null;

    #[ORM\Column(type: Types::DATE_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $inspectionUntil = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 1, nullable: true)]
    #[Assert\PositiveOrZero]
    private ?string $meterValue = null;

    #[ORM\Column(length: 3, nullable: true, enumType: MeterUnit::class)]
    private ?MeterUnit $meterUnit = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $meterUpdatedAt = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $photoPath = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $notes = null;

    #[ORM\Column]
    private bool $active = true;

    /** Ośrodek kosztów maszyny – tu trafiają przypisane do niej faktury i pozycje. */
    #[ORM\OneToOne]
    #[ORM\JoinColumn(onDelete: 'SET NULL')]
    private ?CostCenter $costCenter = null;

    /** @var Collection<int, Machine> */
    #[ORM\ManyToMany(targetEntity: self::class)]
    #[ORM\JoinTable(name: 'machine_set_component')]
    #[ORM\JoinColumn(name: 'set_id', onDelete: 'CASCADE')]
    #[ORM\InverseJoinColumn(name: 'machine_id', onDelete: 'CASCADE')]
    #[ORM\OrderBy(['name' => 'ASC'])]
    private Collection $components;

    /** @var Collection<int, MachineTare> */
    #[ORM\OneToMany(targetEntity: MachineTare::class, mappedBy: 'machine', cascade: ['persist'])]
    #[ORM\OrderBy(['measuredAt' => 'DESC', 'id' => 'DESC'])]
    private Collection $tareHistory;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $createdAt;

    public function __construct()
    {
        $this->components = new ArrayCollection();
        $this->tareHistory = new ArrayCollection();
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): static
    {
        $this->name = $name;

        return $this;
    }

    public function getCategory(): ?MachineCategory
    {
        return $this->category;
    }

    public function setCategory(?MachineCategory $category): static
    {
        $this->category = $category;

        return $this;
    }

    public function getRegistrationNumber(): ?string
    {
        return $this->registrationNumber;
    }

    public function setRegistrationNumber(?string $registrationNumber): static
    {
        $registrationNumber = null !== $registrationNumber ? mb_strtoupper(trim($registrationNumber)) : null;
        $this->registrationNumber = '' === $registrationNumber ? null : $registrationNumber;

        return $this;
    }

    public function getBrand(): ?string
    {
        return $this->brand;
    }

    public function setBrand(?string $brand): static
    {
        $this->brand = $brand;

        return $this;
    }

    public function getModel(): ?string
    {
        return $this->model;
    }

    public function setModel(?string $model): static
    {
        $this->model = $model;

        return $this;
    }

    public function getBrandAndModel(): ?string
    {
        $label = trim(($this->brand ?? '').' '.($this->model ?? ''));

        return '' === $label ? null : $label;
    }

    public function getProductionYear(): ?int
    {
        return $this->productionYear;
    }

    public function setProductionYear(?int $productionYear): static
    {
        $this->productionYear = $productionYear;

        return $this;
    }

    public function getSerialNumber(): ?string
    {
        return $this->serialNumber;
    }

    public function setSerialNumber(?string $serialNumber): static
    {
        $this->serialNumber = $serialNumber;

        return $this;
    }

    public function isOwned(): bool
    {
        return $this->owned;
    }

    public function setOwned(bool $owned): static
    {
        $this->owned = $owned;

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

    public function getTareKg(): ?int
    {
        return $this->tareKg;
    }

    public function getTareSetAt(): ?\DateTimeImmutable
    {
        return $this->tareSetAt;
    }

    public function getTareSource(): ?TareSource
    {
        return $this->tareSource;
    }

    /**
     * Nowa tara trafia też do historii; ta sama wartość niczego nie zmienia.
     */
    public function recordTare(int $kg, TareSource $source, ?User $user, ?\DateTimeImmutable $at = null): ?MachineTare
    {
        if ($kg === $this->tareKg) {
            return null;
        }

        $at ??= new \DateTimeImmutable();
        $this->tareKg = $kg;
        $this->tareSetAt = $at;
        $this->tareSource = $source;

        $entry = new MachineTare($this, $kg, $source, $at, $user);
        $this->tareHistory->add($entry);

        return $entry;
    }

    public function clearTare(): static
    {
        $this->tareKg = null;
        $this->tareSetAt = null;
        $this->tareSource = null;

        return $this;
    }

    /**
     * @return Collection<int, MachineTare>
     */
    public function getTareHistory(): Collection
    {
        return $this->tareHistory;
    }

    /**
     * Suma tar składników zestawu, jeśli każdy ma tarę.
     */
    public function componentsTareKg(): ?int
    {
        if ($this->components->isEmpty()) {
            return null;
        }

        $sum = 0;
        foreach ($this->components as $component) {
            if (null === $component->getTareKg()) {
                return null;
            }
            $sum += $component->getTareKg();
        }

        return $sum;
    }

    public function getPayloadKg(): ?int
    {
        return $this->payloadKg;
    }

    public function setPayloadKg(?int $payloadKg): static
    {
        $this->payloadKg = $payloadKg;

        return $this;
    }

    public function getInsuranceUntil(): ?\DateTimeImmutable
    {
        return $this->insuranceUntil;
    }

    public function setInsuranceUntil(?\DateTimeImmutable $insuranceUntil): static
    {
        $this->insuranceUntil = $insuranceUntil;

        return $this;
    }

    public function getInspectionUntil(): ?\DateTimeImmutable
    {
        return $this->inspectionUntil;
    }

    public function setInspectionUntil(?\DateTimeImmutable $inspectionUntil): static
    {
        $this->inspectionUntil = $inspectionUntil;

        return $this;
    }

    public function getMeterValue(): ?string
    {
        return $this->meterValue;
    }

    public function setMeterValue(?string $meterValue): static
    {
        if ($meterValue !== $this->meterValue) {
            $this->meterUpdatedAt = null !== $meterValue ? new \DateTimeImmutable() : null;
        }
        $this->meterValue = $meterValue;

        return $this;
    }

    /**
     * Stan licznika z wpisu serwisowego – zapisujemy tylko wyższy od obecnego.
     */
    public function raiseMeter(?string $value, \DateTimeImmutable $at): bool
    {
        if (null === $value || (null !== $this->meterValue && (float) $value <= (float) $this->meterValue)) {
            return false;
        }

        $this->meterValue = $value;
        $this->meterUpdatedAt = $at;

        return true;
    }

    public function getMeterUnit(): ?MeterUnit
    {
        return $this->meterUnit;
    }

    public function setMeterUnit(?MeterUnit $meterUnit): static
    {
        $this->meterUnit = $meterUnit;

        return $this;
    }

    public function getMeterUpdatedAt(): ?\DateTimeImmutable
    {
        return $this->meterUpdatedAt;
    }

    public function getPhotoPath(): ?string
    {
        return $this->photoPath;
    }

    public function setPhotoPath(?string $photoPath): static
    {
        $this->photoPath = $photoPath;

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

    public function isActive(): bool
    {
        return $this->active;
    }

    public function setActive(bool $active): static
    {
        $this->active = $active;

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

    /**
     * @return Collection<int, Machine>
     */
    public function getComponents(): Collection
    {
        return $this->components;
    }

    public function addComponent(Machine $component): static
    {
        if ($component !== $this && !$this->components->contains($component)) {
            $this->components->add($component);
        }

        return $this;
    }

    public function removeComponent(Machine $component): static
    {
        $this->components->removeElement($component);

        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    #[Assert\Callback]
    public function validate(ExecutionContextInterface $context): void
    {
        if (null !== $this->meterValue && null === $this->meterUnit) {
            $context->buildViolation('Wybierz, czy licznik liczy motogodziny, czy kilometry.')->atPath('meterUnit')->addViolation();
        }
        if ($this->components->contains($this)) {
            $context->buildViolation('Zestaw nie może zawierać samego siebie.')->atPath('components')->addViolation();
        }
    }

    public function getLabel(): string
    {
        return null !== $this->registrationNumber ? $this->name.' ('.$this->registrationNumber.')' : $this->name;
    }

    public function __toString(): string
    {
        return $this->getLabel();
    }
}
