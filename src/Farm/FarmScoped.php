<?php

declare(strict_types=1);

namespace App\Farm;

use App\Entity\Farm;

/**
 * Encja należąca do gospodarstwa. Zapytania o takie encje są automatycznie
 * zawężane do bieżącego gospodarstwa (FarmFilter), a przy zapisie gospodarstwo
 * uzupełnia się samo (FarmAssignListener).
 */
interface FarmScoped
{
    public function getFarm(): ?Farm;

    public function setFarm(Farm $farm): static;
}
