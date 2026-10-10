<?php

declare(strict_types=1);

namespace App\Entity;

use App\Farm\FarmScoped;
use App\Repository\AuditLogRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * Wpis dziennika zdarzeń. Zdarzenia niezwiązane z gospodarstwem (np. rejestracja konta) nie mają gospodarstwa.
 */
#[ORM\Entity(repositoryClass: AuditLogRepository::class)]
#[ORM\Index(columns: ['entity', 'entity_id'])]
#[ORM\Index(columns: ['farm_id', 'created_at'])]
class AuditLog implements FarmScoped
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(onDelete: 'CASCADE')]
    private ?Farm $farm = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $createdAt;

    /**
     * @param array<string, mixed> $changes
     */
    public function __construct(
        #[ORM\ManyToOne]
        #[ORM\JoinColumn(onDelete: 'SET NULL')]
        private ?User $user,
        #[ORM\Column(length: 60)]
        private string $entity,
        #[ORM\Column(length: 40)]
        private string $entityId,
        #[ORM\Column(length: 60)]
        private string $action,
        #[ORM\Column(type: Types::JSON)]
        private array $changes = [],
    ) {
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getFarm(): ?Farm
    {
        return $this->farm;
    }

    public function setFarm(Farm $farm): static
    {
        $this->farm = $farm;

        return $this;
    }

    public function getUser(): ?User
    {
        return $this->user;
    }

    public function getEntity(): string
    {
        return $this->entity;
    }

    public function getEntityId(): string
    {
        return $this->entityId;
    }

    public function getAction(): string
    {
        return $this->action;
    }

    /**
     * @return array<string, mixed>
     */
    public function getChanges(): array
    {
        return $this->changes;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }
}
