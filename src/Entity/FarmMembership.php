<?php

declare(strict_types=1);

namespace App\Entity;

use App\Enum\UserRole;
use App\Repository\FarmMembershipRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * Dostęp użytkownika do gospodarstwa wraz z rolą w tym gospodarstwie.
 */
#[ORM\Entity(repositoryClass: FarmMembershipRepository::class)]
#[ORM\UniqueConstraint(columns: ['farm_id', 'user_id'])]
class FarmMembership
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $createdAt;

    /**
     * Widżety pulpitu w kolejności; null = układ domyślny.
     *
     * @var list<string>|null
     */
    #[ORM\Column(type: Types::JSON, nullable: true)]
    private ?array $dashboardWidgets = null;

    public function __construct(
        #[ORM\ManyToOne]
        #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
        private Farm $farm,
        #[ORM\ManyToOne]
        #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
        private User $user,
        #[ORM\Column(length: 20, enumType: UserRole::class)]
        private UserRole $role = UserRole::Worker,
    ) {
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getFarm(): Farm
    {
        return $this->farm;
    }

    public function getUser(): User
    {
        return $this->user;
    }

    public function getRole(): UserRole
    {
        return $this->role;
    }

    public function setRole(UserRole $role): static
    {
        $this->role = $role;

        return $this;
    }

    public function isOwner(): bool
    {
        return UserRole::Owner === $this->role;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    /**
     * @return list<string>|null
     */
    public function getDashboardWidgets(): ?array
    {
        return null !== $this->dashboardWidgets ? array_values(array_filter($this->dashboardWidgets, 'is_string')) : null;
    }

    /**
     * @param list<string>|null $widgets
     */
    public function setDashboardWidgets(?array $widgets): static
    {
        $this->dashboardWidgets = $widgets;

        return $this;
    }
}
