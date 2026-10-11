<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Contractor;
use App\Entity\ItemMapping;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ItemMapping>
 */
class ItemMappingRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ItemMapping::class);
    }

    /**
     * Zapamiętane mapowania dostawcy, indeksowane kluczem nazwy pozycji.
     *
     * @return array<string, ItemMapping>
     */
    public function forContractor(?Contractor $contractor): array
    {
        $qb = $this->createQueryBuilder('m')->addSelect('p')->leftJoin('m.product', 'p');
        null === $contractor ? $qb->where('m.contractor IS NULL') : $qb->where('m.contractor = :contractor')->setParameter('contractor', $contractor);

        $mappings = [];
        foreach ($qb->getQuery()->getResult() as $mapping) {
            $mappings[$mapping->getNameKey()] = $mapping;
        }

        return $mappings;
    }

    /**
     * Ta sama nazwa pozycji przypisana u innych dostawców – mocna propozycja.
     *
     * @param list<string> $keys
     *
     * @return array<string, ItemMapping>
     */
    public function findAnyByKeys(array $keys): array
    {
        if ([] === $keys) {
            return [];
        }

        $mappings = [];
        foreach ($this->createQueryBuilder('m')
            ->addSelect('p')
            ->join('m.product', 'p')
            ->where('m.nameKey IN (:keys)')
            ->andWhere('p.active = true')
            ->setParameter('keys', $keys)
            ->orderBy('m.uses', 'ASC')
            ->getQuery()
            ->getResult() as $mapping) {
            $mappings[$mapping->getNameKey()] = $mapping;
        }

        return $mappings;
    }
}
