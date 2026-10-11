<?php

declare(strict_types=1);

namespace App\Entity;

use App\Farm\BelongsToFarm;
use App\Farm\FarmScoped;
use App\Repository\ProductRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Towar z kartoteki – to do niego przypisuje się pozycje faktur.
 */
#[ORM\Entity(repositoryClass: ProductRepository::class)]
#[ORM\Index(columns: ['farm_id', 'active', 'name'])]
class Product implements \Stringable, FarmScoped
{
    use BelongsToFarm;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 160)]
    #[Assert\NotBlank(message: 'Podaj nazwę towaru.')]
    #[Assert\Length(max: 160)]
    private string $name = '';

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(onDelete: 'SET NULL')]
    private ?ProductCategory $category = null;

    /** Jednostka, w której liczymy ilości, ceny i zużycie. */
    #[ORM\ManyToOne]
    #[ORM\JoinColumn(onDelete: 'SET NULL')]
    #[Assert\NotNull(message: 'Wybierz jednostkę.')]
    private ?Unit $baseUnit = null;

    #[ORM\Column(length: 40, nullable: true)]
    #[Assert\Length(max: 40)]
    private ?string $internalCode = null;

    #[ORM\Column(length: 80, nullable: true)]
    #[Assert\Length(max: 80)]
    private ?string $catalogNumber = null;

    /** Czas dostawy w dniach – przypomnienie o zamówieniu przychodzi o tyle wcześniej. */
    #[ORM\Column(type: Types::SMALLINT, nullable: true)]
    #[Assert\Range(min: 0, max: 365)]
    private ?int $leadTimeDays = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 15, scale: 4, nullable: true)]
    #[Assert\PositiveOrZero]
    private ?string $bookPrice = null;

    #[ORM\Column]
    private bool $active = true;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $notes = null;

    /** @var Collection<int, ProductUnitConversion> */
    #[ORM\OneToMany(targetEntity: ProductUnitConversion::class, mappedBy: 'product', cascade: ['persist', 'remove'], orphanRemoval: true)]
    #[Assert\Valid]
    private Collection $conversions;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $createdAt;

    public function __construct()
    {
        $this->conversions = new ArrayCollection();
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
        $this->name = trim($name);

        return $this;
    }

    public function getCategory(): ?ProductCategory
    {
        return $this->category;
    }

    public function setCategory(?ProductCategory $category): static
    {
        $this->category = $category;

        return $this;
    }

    public function getBaseUnit(): ?Unit
    {
        return $this->baseUnit;
    }

    public function setBaseUnit(?Unit $baseUnit): static
    {
        $this->baseUnit = $baseUnit;

        return $this;
    }

    public function getUnitSymbol(): string
    {
        return $this->baseUnit?->getSymbol() ?? '';
    }

    public function getInternalCode(): ?string
    {
        return $this->internalCode;
    }

    public function setInternalCode(?string $internalCode): static
    {
        $this->internalCode = $internalCode;

        return $this;
    }

    public function getCatalogNumber(): ?string
    {
        return $this->catalogNumber;
    }

    public function setCatalogNumber(?string $catalogNumber): static
    {
        $this->catalogNumber = $catalogNumber;

        return $this;
    }

    public function getLeadTimeDays(): ?int
    {
        return $this->leadTimeDays;
    }

    public function setLeadTimeDays(?int $leadTimeDays): static
    {
        $this->leadTimeDays = $leadTimeDays;

        return $this;
    }

    public function getBookPrice(): ?string
    {
        return $this->bookPrice;
    }

    public function setBookPrice(?string $bookPrice): static
    {
        $this->bookPrice = $bookPrice;

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
     * @return Collection<int, ProductUnitConversion>
     */
    public function getConversions(): Collection
    {
        return $this->conversions;
    }

    public function addConversion(ProductUnitConversion $conversion): static
    {
        if (!$this->conversions->contains($conversion)) {
            $conversion->setProduct($this);
            $this->conversions->add($conversion);
        }

        return $this;
    }

    public function removeConversion(ProductUnitConversion $conversion): static
    {
        $this->conversions->removeElement($conversion);

        return $this;
    }

    /**
     * Ile jednostek podstawowych to jedna podana jednostka (np. 1 bela = 450 kg). Null, gdy nie wiadomo.
     */
    public function factorFor(string $unitSymbol): ?float
    {
        if (0 === strcasecmp($unitSymbol, $this->getUnitSymbol())) {
            return 1.0;
        }
        foreach ($this->conversions as $conversion) {
            if (0 === strcasecmp($unitSymbol, (string) $conversion->getUnit()?->getSymbol())) {
                return (float) $conversion->getFactor();
            }
        }

        return null;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function __toString(): string
    {
        return $this->name;
    }
}
