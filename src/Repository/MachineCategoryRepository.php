<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\MachineCategory;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<MachineCategory>
 */
class MachineCategoryRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, MachineCategory::class);
    }

    /**
     * @return list<MachineCategory>
     */
    public function findOrdered(): array
    {
        return $this->findBy([], ['position' => 'ASC', 'name' => 'ASC']);
    }
}
