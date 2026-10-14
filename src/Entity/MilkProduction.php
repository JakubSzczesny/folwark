<?php

declare(strict_types=1);

namespace App\Entity;

use App\Farm\BelongsToFarm;
use App\Farm\FarmScoped;
use App\Repository\MilkProductionRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Miesięczna produkcja mleka grupy stada – do kosztu pasz na litr.
 */
#[ORM\Entity(repositoryClass: MilkProductionRepository::class)]
#[ORM\UniqueConstraint(columns: ['farm_id', 'cost_center_id', 'month'])]
class MilkProduction implements FarmScoped
{
    use BelongsToFarm;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 12, scale: 1)]
    #[Assert\NotBlank]
    #[Assert\PositiveOrZero]
    private ?string $liters = null;

    public function __construct(
        #[ORM\ManyToOne]
        #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
        private CostCenter $costCenter,
        #[ORM\Column(type: Types::DATE_IMMUTABLE)]
        private \DateTimeImmutable $month,
    ) {
        $this->month = $month->modify('first day of this month')->setTime(0, 0);
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getCostCenter(): CostCenter
    {
        return $this->costCenter;
    }

    public function getMonth(): \DateTimeImmutable
    {
        return $this->month;
    }

    public function getLiters(): ?string
    {
        return $this->liters;
    }

    public function setLiters(?string $liters): static
    {
        $this->liters = $liters;

        return $this;
    }
}
