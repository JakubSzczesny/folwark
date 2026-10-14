<?php

declare(strict_types=1);

namespace App\Entity;

use App\Farm\BelongsToFarm;
use App\Farm\FarmScoped;
use App\Repository\FieldRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Pole uprawne – w kolejnych latach ma sezony upraw, a każdy sezon swój ośrodek kosztów.
 */
#[ORM\Entity(repositoryClass: FieldRepository::class)]
class Field implements \Stringable, FarmScoped
{
    use BelongsToFarm;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 80)]
    #[Assert\NotBlank]
    #[Assert\Length(max: 80)]
    private string $name = '';

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 4)]
    #[Assert\NotBlank(message: 'Podaj powierzchnię.')]
    #[Assert\Positive]
    private ?string $areaHa = null;

    #[ORM\Column(length: 255, nullable: true)]
    #[Assert\Length(max: 255)]
    private ?string $parcelNumbers = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $notes = null;

    #[ORM\Column]
    private bool $active = true;

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

    public function getAreaHa(): ?string
    {
        return $this->areaHa;
    }

    public function setAreaHa(?string $areaHa): static
    {
        $this->areaHa = $areaHa;

        return $this;
    }

    public function getParcelNumbers(): ?string
    {
        return $this->parcelNumbers;
    }

    public function setParcelNumbers(?string $parcelNumbers): static
    {
        $this->parcelNumbers = $parcelNumbers;

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

    public function __toString(): string
    {
        return $this->name;
    }
}
