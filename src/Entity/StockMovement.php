<?php

declare(strict_types=1);

namespace App\Entity;

use App\Farm\BelongsToFarm;
use App\Farm\FarmScoped;
use App\Repository\StockMovementRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * Ruch magazynowy z zatwierdzonego dokumentu: ilość i wartość ze znakiem (+ przyjęcie, − wydanie).
 */
#[ORM\Entity(repositoryClass: StockMovementRepository::class)]
#[ORM\Index(columns: ['farm_id', 'product_id', 'date'])]
class StockMovement implements FarmScoped
{
    use BelongsToFarm;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $createdAt;

    public function __construct(
        #[ORM\ManyToOne]
        #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
        private StockDocument $document,
        #[ORM\ManyToOne]
        #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
        private Product $product,
        #[ORM\ManyToOne]
        #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
        private Warehouse $warehouse,
        #[ORM\Column(type: Types::DATE_IMMUTABLE)]
        private \DateTimeImmutable $date,
        #[ORM\Column(type: Types::DECIMAL, precision: 15, scale: 4)]
        private string $quantity,
        #[ORM\Column(type: Types::DECIMAL, precision: 14, scale: 2)]
        private string $value,
    ) {
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getDocument(): StockDocument
    {
        return $this->document;
    }

    public function getProduct(): Product
    {
        return $this->product;
    }

    public function getWarehouse(): Warehouse
    {
        return $this->warehouse;
    }

    public function getDate(): \DateTimeImmutable
    {
        return $this->date;
    }

    public function getQuantity(): string
    {
        return $this->quantity;
    }

    public function getValue(): string
    {
        return $this->value;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }
}
