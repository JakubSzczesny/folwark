<?php

declare(strict_types=1);

namespace App\Entity;

use App\Farm\BelongsToFarm;
use App\Farm\FarmScoped;
use App\Repository\CropSeasonRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Sezon uprawy na polu (np. pszenica ozima 2026). Koszty przypisane do jego ośrodka dają koszt na hektar.
 */
#[ORM\Entity(repositoryClass: CropSeasonRepository::class)]
#[ORM\Index(columns: ['farm_id', 'year'])]
class CropSeason implements \Stringable, FarmScoped
{
    use BelongsToFarm;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    #[Assert\NotNull(message: 'Wybierz pole.')]
    private ?Field $field = null;

    #[ORM\Column(type: Types::SMALLINT)]
    #[Assert\NotNull]
    #[Assert\Range(min: 2000, max: 2100)]
    private ?int $year = null;

    #[ORM\Column(length: 80)]
    #[Assert\NotBlank(message: 'Podaj uprawę.')]
    #[Assert\Length(max: 80)]
    private string $crop = '';

    /** Powierzchnia obsiana w tym sezonie; puste = całe pole. */
    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 4, nullable: true)]
    #[Assert\Positive]
    private ?string $areaHa = null;

    #[ORM\OneToOne]
    #[ORM\JoinColumn(onDelete: 'SET NULL')]
    private ?CostCenter $costCenter = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getField(): ?Field
    {
        return $this->field;
    }

    public function setField(?Field $field): static
    {
        $this->field = $field;

        return $this;
    }

    public function getYear(): ?int
    {
        return $this->year;
    }

    public function setYear(?int $year): static
    {
        $this->year = $year;

        return $this;
    }

    public function getCrop(): string
    {
        return $this->crop;
    }

    public function setCrop(string $crop): static
    {
        $this->crop = $crop;

        return $this;
    }

    public function getAreaHa(): ?string
    {
        return $this->areaHa;
    }

    public function setAreaHa(?string $areaHa): static
    {
        $this->areaHa = $areaHa;

        return $this;
    }

    public function effectiveArea(): float
    {
        return (float) ($this->areaHa ?? $this->field?->getAreaHa() ?? 0);
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

    public function getLabel(): string
    {
        return \sprintf('%s – %s %d', $this->field?->getName() ?? '?', $this->crop, (int) $this->year);
    }

    public function __toString(): string
    {
        return $this->getLabel();
    }
}
