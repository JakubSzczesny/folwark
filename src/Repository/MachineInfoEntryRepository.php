<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Machine;
use App\Entity\MachineInfoEntry;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<MachineInfoEntry>
 */
class MachineInfoEntryRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, MachineInfoEntry::class);
    }

    /**
     * @return array<int, int> id kategorii => liczba wpisów maszyny
     */
    public function countByCategory(Machine $machine): array
    {
        $rows = $this->createQueryBuilder('e')
            ->select('IDENTITY(e.category) AS category, COUNT(e.id) AS entries')
            ->where('e.machine = :machine')
            ->setParameter('machine', $machine)
            ->groupBy('e.category')
            ->getQuery()
            ->getArrayResult();

        $counts = [];
        foreach ($rows as $row) {
            $counts[(int) $row['category']] = (int) $row['entries'];
        }

        return $counts;
    }

    /**
     * @param list<int> $categoryIds
     *
     * @return list<MachineInfoEntry>
     */
    public function findForMachine(Machine $machine, array $categoryIds): array
    {
        if ([] === $categoryIds) {
            return [];
        }

        return $this->createQueryBuilder('e')
            ->addSelect('a')
            ->leftJoin('e.attachments', 'a')
            ->where('e.machine = :machine')
            ->andWhere('e.category IN (:categories)')
            ->setParameter('machine', $machine)
            ->setParameter('categories', $categoryIds)
            ->orderBy('e.name', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
