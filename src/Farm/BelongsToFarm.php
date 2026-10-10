<?php

declare(strict_types=1);

namespace App\Farm;

use App\Entity\Farm;
use Doctrine\ORM\Mapping as ORM;

trait BelongsToFarm
{
    #[ORM\ManyToOne(targetEntity: Farm::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Farm $farm = null;

    public function getFarm(): ?Farm
    {
        return $this->farm;
    }

    public function setFarm(Farm $farm): static
    {
        $this->farm = $farm;

        return $this;
    }
}
