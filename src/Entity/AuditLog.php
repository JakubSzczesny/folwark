<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\AuditLogRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: AuditLogRepository::class)]
#[ORM\Index(columns: ['entity', 'entity_id'])]
#[ORM\Index(columns: ['created_at'])]
class AuditLog
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

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
