<?php

declare(strict_types=1);

namespace App\Farm;

use App\Entity\Farm;
use App\Enum\UserRole;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Contracts\Service\ResetInterface;

/**
 * Gospodarstwo, w którym pracuje bieżące żądanie (z sesji) albo zadanie w tle.
 */
class FarmContext implements ResetInterface
{
    private ?Farm $farm = null;
    private ?UserRole $role = null;

    public function __construct(
        private readonly EntityManagerInterface $em,
    ) {
    }

    public function set(Farm $farm, ?UserRole $role = null): void
    {
        $id = $farm->getId();
        if (null === $id) {
            throw new \LogicException('Gospodarstwo musi być zapisane, zanim stanie się kontekstem.');
        }

        $this->farm = $farm;
        $this->role = $role;

        $filters = $this->em->getFilters();
        $filter = $filters->isEnabled(FarmFilter::NAME) ? $filters->getFilter(FarmFilter::NAME) : $filters->enable(FarmFilter::NAME);
        $filter->setParameter('farm', $id, Types::INTEGER);
    }

    public function clear(): void
    {
        $this->farm = null;
        $this->role = null;

        if ($this->em->getFilters()->isEnabled(FarmFilter::NAME)) {
            $this->em->getFilters()->disable(FarmFilter::NAME);
        }
    }

    public function getFarm(): ?Farm
    {
        return $this->farm;
    }

    public function farm(): Farm
    {
        return $this->farm ?? throw new \LogicException('Nie wybrano gospodarstwa.');
    }

    public function farmId(): ?int
    {
        return $this->farm?->getId();
    }

    public function getRole(): ?UserRole
    {
        return $this->role;
    }

    public function reset(): void
    {
        $this->clear();
    }
}
