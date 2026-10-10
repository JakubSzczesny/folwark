<?php

declare(strict_types=1);

namespace App\Entity;

use App\Farm\BelongsToFarm;
use App\Farm\FarmScoped;
use App\Repository\KsefSyncRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: KsefSyncRepository::class)]
#[ORM\Index(columns: ['farm_id', 'started_at'])]
class KsefSync implements FarmScoped
{
    use BelongsToFarm;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $startedAt;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $finishedAt = null;

    #[ORM\Column]
    private int $newCount = 0;

    #[ORM\Column]
    private int $newCostCount = 0;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $error = null;

    public function __construct(
        #[ORM\Column]
        private bool $manual = false,
        #[ORM\ManyToOne]
        #[ORM\JoinColumn(onDelete: 'SET NULL')]
        private ?User $user = null,
    ) {
        $this->startedAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getStartedAt(): \DateTimeImmutable
    {
        return $this->startedAt;
    }

    public function getFinishedAt(): ?\DateTimeImmutable
    {
        return $this->finishedAt;
    }

    public function isRunning(): bool
    {
        return null === $this->finishedAt;
    }

    public function getNewCount(): int
    {
        return $this->newCount;
    }

    public function getNewCostCount(): int
    {
        return $this->newCostCount;
    }

    public function getError(): ?string
    {
        return $this->error;
    }

    public function isFailed(): bool
    {
        return null !== $this->error;
    }

    public function isManual(): bool
    {
        return $this->manual;
    }

    public function getUser(): ?User
    {
        return $this->user;
    }

    public function finish(int $newCount, int $newCostCount, ?string $error = null): void
    {
        $this->finishedAt = new \DateTimeImmutable();
        $this->newCount = $newCount;
        $this->newCostCount = $newCostCount;
        $this->error = $error;
    }
}
