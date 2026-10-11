<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\OrderSettings;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<OrderSettings>
 */
class OrderSettingsRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, OrderSettings::class);
    }

    /**
     * @return array<string, OrderSettings> klucz „p:{id}” albo „c:{id}”
     */
    public function findIndexed(): array
    {
        $indexed = [];
        foreach ($this->findAll() as $settings) {
            $key = null !== $settings->getProduct() ? 'p:'.$settings->getProduct()->getId() : 'c:'.$settings->getContractor()?->getId();
            $indexed[$key] = $settings;
        }

        return $indexed;
    }
}
