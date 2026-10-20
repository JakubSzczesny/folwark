<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\InvoiceItemRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: InvoiceItemRepository::class)]
class InvoiceItem
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 15, scale: 4, nullable: true)]
    private ?string $quantity = null;

    #[ORM\Column(length: 30, nullable: true)]
    private ?string $unit = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 15, scale: 4, nullable: true)]
    private ?string $unitPriceNet = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 14, scale: 2, nullable: true)]
    private ?string $netValue = null;

    #[ORM\Column(length: 10, nullable: true)]
    private ?string $vatRate = null;

    /** Wiersz „stan przed korektą” – wartości są wtedy zapisane ze znakiem minus. */
    #[ORM\Column]
    private bool $beforeCorrection = false;

    #[ORM\Column]
    private bool $skipped = false;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(onDelete: 'SET NULL')]
    private ?CostCenter $costCenter = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(onDelete: 'SET NULL')]
    private ?Product $product = null;

    /** Ilość przeliczona na jednostkę podstawową towaru (np. 4 kanistry × 5 l = 20 l). */
    #[ORM\Column(type: Types::DECIMAL, precision: 15, scale: 4, nullable: true)]
    private ?string $quantityInProductUnit = null;

    public function __construct(
        #[ORM\ManyToOne(inversedBy: 'items')]
        #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
        private Invoice $invoice,
        #[ORM\Column]
        private int $position,
        #[ORM\Column(length: 512)]
        private string $name,
    ) {
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getInvoice(): Invoice
    {
        return $this->invoice;
    }

    public function getPosition(): int
    {
        return $this->position;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getQuantity(): ?string
    {
        return $this->quantity;
    }

    public function getUnit(): ?string
    {
        return $this->unit;
    }

    public function getUnitPriceNet(): ?string
    {
        return $this->unitPriceNet;
    }

    public function getNetValue(): ?string
    {
        return $this->netValue;
    }

    public function getVatRate(): ?string
    {
        return $this->vatRate;
    }

    public function setValues(?string $quantity, ?string $unit, ?string $unitPriceNet, ?string $netValue, ?string $vatRate): static
    {
        $this->quantity = $quantity;
        $this->unit = $unit;
        $this->unitPriceNet = $unitPriceNet;
        $this->netValue = $netValue;
        $this->vatRate = $vatRate;

        return $this;
    }

    public function isBeforeCorrection(): bool
    {
        return $this->beforeCorrection;
    }

    public function setBeforeCorrection(bool $beforeCorrection): static
    {
        $this->beforeCorrection = $beforeCorrection;

        return $this;
    }

    public function isSkipped(): bool
    {
        return $this->skipped;
    }

    public function setSkipped(bool $skipped): static
    {
        $this->skipped = $skipped;
        if ($skipped) {
            $this->product = null;
            $this->quantityInProductUnit = null;
        }

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

    public function getProduct(): ?Product
    {
        return $this->product;
    }

    /**
     * Pozycja bieżąca (nie stan przed korektą) bez towaru i bez „pomiń”.
     */
    public function isUnassigned(): bool
    {
        return null === $this->product && !$this->skipped && !$this->beforeCorrection;
    }

    public function getQuantityInProductUnit(): ?string
    {
        return $this->quantityInProductUnit;
    }

    /**
     * Przypisanie towaru; $factor to liczba jednostek podstawowych w jednej jednostce z faktury.
     */
    public function assignProduct(?Product $product, ?float $factor): static
    {
        $this->product = $product;
        $this->skipped = false;
        $this->quantityInProductUnit = null !== $product && null !== $factor && null !== $this->quantity
            ? number_format((float) $this->quantity * $factor, 4, '.', '')
            : null;

        return $this;
    }
}
