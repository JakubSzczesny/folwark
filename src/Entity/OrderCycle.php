<?php

declare(strict_types=1);

namespace App\Entity;

use App\Enum\OrderMode;
use App\Enum\OrderRegularity;
use App\Enum\OrderStatus;
use App\Farm\BelongsToFarm;
use App\Farm\FarmScoped;
use App\Order\Prediction;
use App\Repository\OrderCycleRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * Cykl zamówienia towaru (albo dostawcy, gdy jego towary nie są przypisane): od ostatniego zamówienia
 * do przewidywanego następnego. Nowa faktura zamyka cykl i otwiera następny.
 */
#[ORM\Entity(repositoryClass: OrderCycleRepository::class)]
#[ORM\Index(columns: ['farm_id', 'closed_at', 'status'])]
#[ORM\Index(columns: ['farm_id', 'predicted_date'])]
class OrderCycle implements FarmScoped
{
    use BelongsToFarm;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    /** Ostatnie zamówienie, na którym opiera się przewidywanie. */
    #[ORM\Column(type: Types::DATE_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $lastOrderDate = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 15, scale: 3, nullable: true)]
    private ?string $lastOrderQuantity = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 15, scale: 4, nullable: true)]
    private ?string $lastUnitPrice = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 15, scale: 4, nullable: true)]
    private ?string $medianUnitPrice = null;

    #[ORM\Column(type: Types::DATE_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $predictedDate = null;

    #[ORM\Column(type: Types::DATE_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $windowFrom = null;

    #[ORM\Column(type: Types::DATE_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $windowTo = null;

    #[ORM\Column(type: Types::DATE_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $reminderDate = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 15, scale: 3, nullable: true)]
    private ?string $typicalQuantity = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 14, scale: 2, nullable: true)]
    private ?string $typicalAmount = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 15, scale: 4, nullable: true)]
    private ?string $dailyUsage = null;

    #[ORM\Column(length: 12, nullable: true, enumType: OrderMode::class)]
    private ?OrderMode $mode = null;

    #[ORM\Column(length: 12, nullable: true, enumType: OrderRegularity::class)]
    private ?OrderRegularity $regularity = null;

    #[ORM\Column(type: Types::SMALLINT, nullable: true)]
    private ?int $intervalDays = null;

    #[ORM\Column(type: Types::SMALLINT, nullable: true)]
    private ?int $spreadDays = null;

    #[ORM\Column(type: Types::SMALLINT)]
    private int $orderCount = 0;

    #[ORM\Column(length: 14, enumType: OrderStatus::class)]
    private OrderStatus $status = OrderStatus::Insufficient;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(onDelete: 'SET NULL')]
    private ?User $markedBy = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $markedAt = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $notes = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $closedAt = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $updatedAt;

    public function __construct(
        #[ORM\ManyToOne]
        #[ORM\JoinColumn(onDelete: 'CASCADE')]
        private ?Product $product = null,
        #[ORM\ManyToOne]
        #[ORM\JoinColumn(onDelete: 'CASCADE')]
        private ?Contractor $contractor = null,
    ) {
        if ((null === $product) === (null === $contractor)) {
            throw new \InvalidArgumentException('Cykl dotyczy albo towaru, albo dostawcy.');
        }
        $this->updatedAt = new \DateTimeImmutable();
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

    public function getLabel(): string
    {
        return $this->product?->getName() ?? $this->contractor?->getDisplayName() ?? '';
    }

    /**
     * Przepisuje wyliczenia; status ustawiony ręcznie (zamówione, pominięte) zostaje.
     */
    public function apply(Prediction $prediction): void
    {
        $last = $prediction->lastOrder();
        $this->lastOrderDate = $last?->date;
        $this->lastOrderQuantity = self::decimal($last?->quantity, 3);
        $this->lastUnitPrice = self::decimal($last?->unitPrice(), 4);
        $this->medianUnitPrice = self::decimal($prediction->medianUnitPrice, 4);
        $this->predictedDate = $prediction->predictedDate;
        $this->windowFrom = $prediction->windowFrom;
        $this->windowTo = $prediction->windowTo;
        $this->reminderDate = $prediction->reminderDate;
        $this->typicalQuantity = self::decimal($prediction->typicalQuantity, 3);
        $this->typicalAmount = self::decimal($prediction->typicalAmount, 2);
        $this->dailyUsage = self::decimal($prediction->dailyUsage, 4);
        $this->mode = $prediction->mode;
        $this->regularity = $prediction->regularity;
        $this->intervalDays = $prediction->intervalDays;
        $this->spreadDays = $prediction->spreadDays;
        $this->orderCount = \count($prediction->orders);
        $this->updatedAt = new \DateTimeImmutable();
    }

    /**
     * Status wynikający z dat; nie rusza „zamówione” w okresie wstrzymania ani „pominięte”.
     */
    public function refreshStatus(\DateTimeImmutable $today, int $orderedHoldDays): void
    {
        if (OrderStatus::Skipped === $this->status) {
            return;
        }
        if (OrderStatus::Ordered === $this->status && null !== $this->markedAt && $this->markedAt->modify(\sprintf('+%d days', $orderedHoldDays)) > $today) {
            return;
        }

        $this->status = match (true) {
            null === $this->predictedDate => OrderStatus::Insufficient,
            null !== $this->windowTo && $today > $this->windowTo => OrderStatus::Overdue,
            null !== $this->reminderDate && $today >= $this->reminderDate => OrderStatus::Approaching,
            default => OrderStatus::Expected,
        };
    }

    public function mark(OrderStatus $status, ?User $user): void
    {
        $this->status = $status;
        $this->markedBy = $user;
        $this->markedAt = new \DateTimeImmutable();
    }

    public function close(): void
    {
        $this->closedAt = new \DateTimeImmutable();
    }

    public function isClosed(): bool
    {
        return null !== $this->closedAt;
    }

    public function getLastOrderDate(): ?\DateTimeImmutable
    {
        return $this->lastOrderDate;
    }

    public function getLastOrderQuantity(): ?string
    {
        return $this->lastOrderQuantity;
    }

    public function getLastUnitPrice(): ?string
    {
        return $this->lastUnitPrice;
    }

    public function getMedianUnitPrice(): ?string
    {
        return $this->medianUnitPrice;
    }

    public function priceChange(): ?float
    {
        if (null === $this->lastUnitPrice || null === $this->medianUnitPrice || (float) $this->medianUnitPrice <= 0) {
            return null;
        }

        return (float) $this->lastUnitPrice / (float) $this->medianUnitPrice - 1;
    }

    public function getPredictedDate(): ?\DateTimeImmutable
    {
        return $this->predictedDate;
    }

    public function getWindowFrom(): ?\DateTimeImmutable
    {
        return $this->windowFrom;
    }

    public function getWindowTo(): ?\DateTimeImmutable
    {
        return $this->windowTo;
    }

    public function getReminderDate(): ?\DateTimeImmutable
    {
        return $this->reminderDate;
    }

    public function getTypicalQuantity(): ?string
    {
        return $this->typicalQuantity;
    }

    public function getTypicalAmount(): ?string
    {
        return $this->typicalAmount;
    }

    public function getDailyUsage(): ?string
    {
        return $this->dailyUsage;
    }

    public function getMode(): ?OrderMode
    {
        return $this->mode;
    }

    public function getRegularity(): ?OrderRegularity
    {
        return $this->regularity;
    }

    public function getIntervalDays(): ?int
    {
        return $this->intervalDays;
    }

    public function getSpreadDays(): ?int
    {
        return $this->spreadDays;
    }

    public function getOrderCount(): int
    {
        return $this->orderCount;
    }

    public function getStatus(): OrderStatus
    {
        return $this->status;
    }

    public function getMarkedBy(): ?User
    {
        return $this->markedBy;
    }

    public function getMarkedAt(): ?\DateTimeImmutable
    {
        return $this->markedAt;
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

    public function getClosedAt(): ?\DateTimeImmutable
    {
        return $this->closedAt;
    }

    public function getUpdatedAt(): \DateTimeImmutable
    {
        return $this->updatedAt;
    }

    private static function decimal(?float $value, int $scale): ?string
    {
        return null === $value ? null : number_format($value, $scale, '.', '');
    }
}
