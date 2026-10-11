<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Przelicznik jednostki towaru: 1 {unit} = {factor} jednostek podstawowych, np. 1 bela = 450 kg.
 */
#[ORM\Entity]
#[ORM\UniqueConstraint(columns: ['product_id', 'unit_id'])]
class ProductUnitConversion
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'conversions')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Product $product = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    #[Assert\NotNull(message: 'Wybierz jednostkę.')]
    private ?Unit $unit = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 15, scale: 6)]
    #[Assert\NotBlank]
    #[Assert\Positive]
    private ?string $factor = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getProduct(): ?Product
    {
        return $this->product;
    }

    public function setProduct(Product $product): static
    {
        $this->product = $product;

        return $this;
    }

    public function getUnit(): ?Unit
    {
        return $this->unit;
    }

    public function setUnit(?Unit $unit): static
    {
        $this->unit = $unit;

        return $this;
    }

    public function getFactor(): ?string
    {
        return $this->factor;
    }

    public function setFactor(?string $factor): static
    {
        $this->factor = $factor;

        return $this;
    }
}
