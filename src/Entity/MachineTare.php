<?php

declare(strict_types=1);

namespace App\Entity;

use App\Enum\TareSource;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
class MachineTare
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    public function __construct(
        #[ORM\ManyToOne(inversedBy: 'tareHistory')]
        #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
        private Machine $machine,
        #[ORM\Column]
        private int $tareKg,
        #[ORM\Column(length: 10, enumType: TareSource::class)]
        private TareSource $source,
        #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
        private \DateTimeImmutable $measuredAt,
        #[ORM\ManyToOne]
        #[ORM\JoinColumn(onDelete: 'SET NULL')]
        private ?User $user = null,
    ) {
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getMachine(): Machine
    {
        return $this->machine;
    }

    public function getTareKg(): int
    {
        return $this->tareKg;
    }

    public function getSource(): TareSource
    {
        return $this->source;
    }

    public function getMeasuredAt(): \DateTimeImmutable
    {
        return $this->measuredAt;
    }

    public function getUser(): ?User
    {
        return $this->user;
    }
}
