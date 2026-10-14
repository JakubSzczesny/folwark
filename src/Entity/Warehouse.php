<?php

declare(strict_types=1);

namespace App\Entity;

use App\Enum\WarehouseType;
use App\Farm\BelongsToFarm;
use App\Farm\FarmScoped;
use App\Repository\WarehouseRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: WarehouseRepository::class)]
#[ORM\UniqueConstraint(columns: ['farm_id', 'name'])]
#[UniqueEntity(fields: ['name'], message: 'Taki magazyn już istnieje.')]
class Warehouse implements \Stringable, FarmScoped
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

    #[ORM\Column(length: 20, enumType: WarehouseType::class)]
    private WarehouseType $type = WarehouseType::Hall;

    #[ORM\Column(type: Types::DECIMAL, precision: 14, scale: 3, nullable: true)]
    #[Assert\PositiveOrZero]
    private ?string $capacity = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(onDelete: 'SET NULL')]
    private ?Unit $capacityUnit = null;

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

    public function getType(): WarehouseType
    {
        return $this->type;
    }

    public function setType(WarehouseType $type): static
    {
        $this->type = $type;

        return $this;
    }

    public function getCapacity(): ?string
    {
        return $this->capacity;
    }

    public function setCapacity(?string $capacity): static
    {
        $this->capacity = $capacity;

        return $this;
    }

    public function getCapacityUnit(): ?Unit
    {
        return $this->capacityUnit;
    }

    public function setCapacityUnit(?Unit $capacityUnit): static
    {
        $this->capacityUnit = $capacityUnit;

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
