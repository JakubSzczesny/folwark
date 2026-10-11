<?php

declare(strict_types=1);

namespace App\Entity;

use App\Farm\BelongsToFarm;
use App\Farm\FarmScoped;
use App\Repository\ItemMappingRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * Zapamiętane przypisanie pozycji faktury: „ta nazwa od tego dostawcy to ten towar” (albo „pomiń”).
 * Przy kolejnej fakturze pozycja o tej samej nazwie wypełnia się sama.
 */
#[ORM\Entity(repositoryClass: ItemMappingRepository::class)]
#[ORM\UniqueConstraint(columns: ['farm_id', 'contractor_id', 'name_key'])]
class ItemMapping implements FarmScoped
{
    use BelongsToFarm;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(onDelete: 'CASCADE')]
    private ?Product $product = null;

    #[ORM\Column]
    private bool $skip = false;

    /** Ile jednostek podstawowych towaru to jedna jednostka z faktury (np. kanister 5 l → 5). */
    #[ORM\Column(type: Types::DECIMAL, precision: 15, scale: 6, nullable: true)]
    private ?string $factor = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(onDelete: 'SET NULL')]
    private ?CostCenter $costCenter = null;

    #[ORM\Column]
    private int $uses = 0;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $updatedAt;

    public function __construct(
        #[ORM\ManyToOne]
        #[ORM\JoinColumn(onDelete: 'CASCADE')]
        private ?Contractor $contractor,
        #[ORM\Column(length: 255)]
        private string $nameKey,
    ) {
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getContractor(): ?Contractor
    {
        return $this->contractor;
    }

    public function getNameKey(): string
    {
        return $this->nameKey;
    }

    public function getProduct(): ?Product
    {
        return $this->product;
    }

    public function isSkip(): bool
    {
        return $this->skip;
    }

    public function getFactor(): ?string
    {
        return $this->factor;
    }

    public function getCostCenter(): ?CostCenter
    {
        return $this->costCenter;
    }

    public function getUses(): int
    {
        return $this->uses;
    }

    public function remember(?Product $product, bool $skip, ?string $factor, ?CostCenter $costCenter): static
    {
        $this->product = $skip ? null : $product;
        $this->skip = $skip;
        $this->factor = $skip ? null : $factor;
        $this->costCenter = $costCenter;
        ++$this->uses;
        $this->updatedAt = new \DateTimeImmutable();

        return $this;
    }
}
