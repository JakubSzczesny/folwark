<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

/**
 * Pozycja dokumentu. Ilość i cenę wpisuje się w dowolnej jednostce towaru,
 * a ilość w jednostce podstawowej liczy się z przelicznika.
 */
#[ORM\Entity]
#[ORM\Index(columns: ['product_id'])]
class StockDocumentItem
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'items')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?StockDocument $document = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    #[Assert\NotNull(message: 'Wybierz towar.')]
    private ?Product $product = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 15, scale: 4)]
    #[Assert\NotBlank(message: 'Podaj ilość.')]
    #[Assert\PositiveOrZero]
    private ?string $enteredQuantity = null;

    /** Jednostka wpisana na dokumencie; pusta = jednostka podstawowa towaru. */
    #[ORM\ManyToOne]
    #[ORM\JoinColumn(onDelete: 'SET NULL')]
    private ?Unit $enteredUnit = null;

    /** Ilość w jednostce podstawowej towaru. */
    #[ORM\Column(type: Types::DECIMAL, precision: 15, scale: 4)]
    private string $quantity = '0';

    /** Cena netto za wpisaną jednostkę. */
    #[ORM\Column(type: Types::DECIMAL, precision: 15, scale: 4, nullable: true)]
    #[Assert\PositiveOrZero]
    private ?string $unitPriceNet = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 14, scale: 2, nullable: true)]
    private ?string $netValue = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getDocument(): ?StockDocument
    {
        return $this->document;
    }

    public function setDocument(StockDocument $document): static
    {
        $this->document = $document;

        return $this;
    }

    public function getProduct(): ?Product
    {
        return $this->product;
    }

    public function setProduct(?Product $product): static
    {
        $this->product = $product;

        return $this;
    }

    public function getEnteredQuantity(): ?string
    {
        return $this->enteredQuantity;
    }

    public function setEnteredQuantity(?string $enteredQuantity): static
    {
        $this->enteredQuantity = $enteredQuantity;

        return $this;
    }

    public function getEnteredUnit(): ?Unit
    {
        return $this->enteredUnit;
    }

    public function setEnteredUnit(?Unit $enteredUnit): static
    {
        $this->enteredUnit = $enteredUnit;

        return $this;
    }

    public function getEnteredUnitSymbol(): string
    {
        return $this->enteredUnit?->getSymbol() ?? (string) $this->product?->getUnitSymbol();
    }

    public function getQuantity(): string
    {
        return $this->quantity;
    }

    public function getUnitPriceNet(): ?string
    {
        return $this->unitPriceNet;
    }

    public function setUnitPriceNet(?string $unitPriceNet): static
    {
        $this->unitPriceNet = $unitPriceNet;

        return $this;
    }

    public function getNetValue(): ?string
    {
        return $this->netValue;
    }

    /**
     * Ilość w jednostce podstawowej i wartość pozycji. Wywoływane po zmianie pozycji.
     */
    public function recalculate(): static
    {
        $factor = $this->factor() ?? 1.0;
        $entered = (float) $this->enteredQuantity;
        $this->quantity = number_format($entered * $factor, 4, '.', '');
        $this->netValue = null !== $this->unitPriceNet ? number_format($entered * (float) $this->unitPriceNet, 2, '.', '') : null;

        return $this;
    }

    public function factor(): ?float
    {
        if (null === $this->enteredUnit) {
            return 1.0;
        }

        return $this->product?->factorFor($this->enteredUnit->getSymbol());
    }

    public function copy(): self
    {
        $copy = new self();
        $copy->product = $this->product;
        $copy->enteredQuantity = $this->enteredQuantity;
        $copy->enteredUnit = $this->enteredUnit;
        $copy->quantity = $this->quantity;
        $copy->unitPriceNet = $this->unitPriceNet;
        $copy->netValue = $this->netValue;

        return $copy;
    }

    #[Assert\Callback]
    public function validateUnit(ExecutionContextInterface $context): void
    {
        if (null !== $this->product && null !== $this->enteredUnit && null === $this->factor()) {
            $context->buildViolation(\sprintf('Towar nie ma przelicznika dla „%s” – dodaj go na karcie towaru albo wybierz %s.', $this->enteredUnit->getSymbol(), $this->product->getUnitSymbol() ?: 'inną jednostkę'))
                ->atPath('enteredUnit')
                ->addViolation();
        }
    }
}
