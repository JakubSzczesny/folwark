<?php

declare(strict_types=1);

namespace App\Entity;

use App\Farm\BelongsToFarm;
use App\Farm\FarmScoped;
use App\Repository\StockRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * Stan towaru w magazynie – suma ruchów, trzymana osobno, żeby lista stanów była szybka.
 */
#[ORM\Entity(repositoryClass: StockRepository::class)]
#[ORM\UniqueConstraint(columns: ['farm_id', 'product_id', 'warehouse_id'])]
class Stock implements FarmScoped
{
    use BelongsToFarm;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 15, scale: 4)]
    private string $quantity = '0';

    #[ORM\Column(type: Types::DECIMAL, precision: 14, scale: 2)]
    private string $value = '0';

    #[ORM\Column(type: Types::DATE_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $lastMovementDate = null;

    public function __construct(
        #[ORM\ManyToOne]
        #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
        private Product $product,
        #[ORM\ManyToOne]
        #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
        private Warehouse $warehouse,
    ) {
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getProduct(): Product
    {
        return $this->product;
    }

    public function getWarehouse(): Warehouse
    {
        return $this->warehouse;
    }

    public function getQuantity(): string
    {
        return $this->quantity;
    }

    public function getValue(): string
    {
        return $this->value;
    }

    public function getLastMovementDate(): ?\DateTimeImmutable
    {
        return $this->lastMovementDate;
    }

    /**
     * Średnia ważona cena jednostki podstawowej; null przy zerowym lub ujemnym stanie.
     */
    public function getAveragePrice(): ?float
    {
        $quantity = (float) $this->quantity;

        return $quantity > 0 ? (float) $this->value / $quantity : null;
    }

    public function isNegative(): bool
    {
        return (float) $this->quantity < 0;
    }

    public function apply(float $quantity, float $value, \DateTimeImmutable $date): static
    {
        $newQuantity = round((float) $this->quantity + $quantity, 4);
        $newValue = 0.0 === $newQuantity ? 0.0 : round((float) $this->value + $value, 2);
        $this->quantity = number_format($newQuantity, 4, '.', '');
        $this->value = number_format($newValue, 2, '.', '');
        if (null === $this->lastMovementDate || $date > $this->lastMovementDate) {
            $this->lastMovementDate = $date;
        }

        return $this;
    }
}
