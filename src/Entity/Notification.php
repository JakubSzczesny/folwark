<?php

declare(strict_types=1);

namespace App\Entity;

use App\Enum\NotificationPriority;
use App\Enum\NotificationType;
use App\Farm\BelongsToFarm;
use App\Farm\FarmScoped;
use App\Repository\NotificationRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: NotificationRepository::class)]
#[ORM\UniqueConstraint(columns: ['farm_id', 'user_id', 'dedup_key'])]
#[ORM\Index(columns: ['user_id', 'read_at'])]
class Notification implements FarmScoped
{
    use BelongsToFarm;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $readAt = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $emailedAt = null;

    public function __construct(
        #[ORM\ManyToOne]
        #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
        private User $user,
        #[ORM\Column(length: 30, enumType: NotificationType::class)]
        private NotificationType $type,
        #[ORM\Column(length: 10, enumType: NotificationPriority::class)]
        private NotificationPriority $priority,
        #[ORM\Column(length: 200)]
        private string $title,
        #[ORM\Column(type: Types::TEXT, nullable: true)]
        private ?string $body = null,
        #[ORM\Column(length: 255, nullable: true)]
        private ?string $link = null,
        #[ORM\Column(length: 150, nullable: true)]
        private ?string $dedupKey = null,
    ) {
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getUser(): User
    {
        return $this->user;
    }

    public function getType(): NotificationType
    {
        return $this->type;
    }

    public function getPriority(): NotificationPriority
    {
        return $this->priority;
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function getBody(): ?string
    {
        return $this->body;
    }

    public function getLink(): ?string
    {
        return $this->link;
    }

    public function getDedupKey(): ?string
    {
        return $this->dedupKey;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getReadAt(): ?\DateTimeImmutable
    {
        return $this->readAt;
    }

    public function isRead(): bool
    {
        return null !== $this->readAt;
    }

    public function markRead(): void
    {
        $this->readAt ??= new \DateTimeImmutable();
    }

    public function getEmailedAt(): ?\DateTimeImmutable
    {
        return $this->emailedAt;
    }

    public function markEmailed(): void
    {
        $this->emailedAt = new \DateTimeImmutable();
    }
}
