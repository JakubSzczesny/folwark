<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\TrustedDeviceRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: TrustedDeviceRepository::class)]
class TrustedDevice
{
    public const int MAX_FAILED_ATTEMPTS = 5;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $lastUsedAt = null;

    #[ORM\Column]
    private int $failedAttempts = 0;

    public function __construct(
        #[ORM\ManyToOne]
        #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
        private User $user,
        #[ORM\Column(length: 100)]
        private string $name,
        #[ORM\Column(length: 64, unique: true)]
        private string $tokenHash,
        #[ORM\Column]
        private string $pinHash,
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

    public function getName(): string
    {
        return $this->name;
    }

    public function getTokenHash(): string
    {
        return $this->tokenHash;
    }

    public function getPinHash(): string
    {
        return $this->pinHash;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getLastUsedAt(): ?\DateTimeImmutable
    {
        return $this->lastUsedAt;
    }

    public function getFailedAttempts(): int
    {
        return $this->failedAttempts;
    }

    public function registerSuccess(): void
    {
        $this->failedAttempts = 0;
        $this->lastUsedAt = new \DateTimeImmutable();
    }

    public function registerFailure(): void
    {
        ++$this->failedAttempts;
    }

    public function isLocked(): bool
    {
        return $this->failedAttempts >= self::MAX_FAILED_ATTEMPTS;
    }
}
