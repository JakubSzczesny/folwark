<?php

declare(strict_types=1);

namespace App\Entity;

use App\Farm\BelongsToFarm;
use App\Farm\FarmScoped;
use App\Repository\OrderSettingsRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Ręczne korekty przewidywania dla towaru albo dostawcy.
 */
#[ORM\Entity(repositoryClass: OrderSettingsRepository::class)]
#[ORM\UniqueConstraint(columns: ['farm_id', 'product_id'])]
#[ORM\UniqueConstraint(columns: ['farm_id', 'contractor_id'])]
class OrderSettings implements FarmScoped
{
    use BelongsToFarm;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    /** Własny odstęp, np. „co 21 dni” – zastępuje wyliczony. */
    #[ORM\Column(type: Types::SMALLINT, nullable: true)]
    #[Assert\Range(min: 1, max: 730)]
    private ?int $manualIntervalDays = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 15, scale: 3, nullable: true)]
    #[Assert\Positive]
    private ?string $manualQuantity = null;

    /** Wyprzedzenie przypomnienia; puste = z ustawień gospodarstwa. */
    #[ORM\Column(type: Types::SMALLINT, nullable: true)]
    #[Assert\Range(min: 0, max: 120)]
    private ?int $leadDays = null;

    #[ORM\Column]
    private bool $seasonalDisabled = false;

    #[ORM\Column]
    private bool $hidden = false;

    public function __construct(
        #[ORM\ManyToOne]
        #[ORM\JoinColumn(onDelete: 'CASCADE')]
        private ?Product $product = null,
        #[ORM\ManyToOne]
        #[ORM\JoinColumn(onDelete: 'CASCADE')]
        private ?Contractor $contractor = null,
    ) {
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getProduct(): ?Product
    {
        return $this->product;
    }

    public function getContractor(): ?Contractor
    {
        return $this->contractor;
    }

    public function getManualIntervalDays(): ?int
    {
        return $this->manualIntervalDays;
    }

    public function setManualIntervalDays(?int $manualIntervalDays): static
    {
        $this->manualIntervalDays = $manualIntervalDays;

        return $this;
    }

    public function getManualQuantity(): ?string
    {
        return $this->manualQuantity;
    }

    public function setManualQuantity(?string $manualQuantity): static
    {
        $this->manualQuantity = $manualQuantity;

        return $this;
    }

    public function getLeadDays(): ?int
    {
        return $this->leadDays;
    }

    public function setLeadDays(?int $leadDays): static
    {
        $this->leadDays = $leadDays;

        return $this;
    }

    public function isSeasonalDisabled(): bool
    {
        return $this->seasonalDisabled;
    }

    public function setSeasonalDisabled(bool $seasonalDisabled): static
    {
        $this->seasonalDisabled = $seasonalDisabled;

        return $this;
    }

    public function isHidden(): bool
    {
        return $this->hidden;
    }

    public function setHidden(bool $hidden): static
    {
        $this->hidden = $hidden;

        return $this;
    }
}
