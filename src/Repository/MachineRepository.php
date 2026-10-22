<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Machine;
use App\Entity\MachineInfoEntry;
use App\Ui\Sort;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Machine>
 */
class MachineRepository extends ServiceEntityRepository
{
    public const array SORT = [
        'nazwa' => 'm.name',
        'nr' => 'm.registrationNumber',
        'kategoria' => 'c.position',
        'ubezpieczenie' => 'm.insuranceUntil',
        'przeglad' => 'm.inspectionUntil',
        'serwis' => '(SELECT MAX(sort_s.date) FROM App\Entity\MachineService sort_s WHERE sort_s.machine = m)',
    ];

    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Machine::class);
    }

    /**
     * @param array{q: ?string, category: ?int, deadlines: bool, inactive: bool, until: \DateTimeImmutable} $filters
     */
    public function createListQuery(array $filters, Sort $sort): QueryBuilder
    {
        $qb = $this->createQueryBuilder('m')
            ->addSelect('c')
            ->leftJoin('m.category', 'c');

        if (!$filters['inactive']) {
            $qb->andWhere('m.active = true');
        }
        if (null !== $filters['category']) {
            $qb->andWhere('m.category = :category')->setParameter('category', $filters['category']);
        }
        if (null !== $filters['q'] && '' !== trim($filters['q'])) {
            $qb->andWhere('LOWER(m.name) LIKE :q OR LOWER(m.registrationNumber) LIKE :q OR LOWER(m.brand) LIKE :q OR LOWER(m.model) LIKE :q')
                ->setParameter('q', '%'.mb_strtolower(trim($filters['q'])).'%');
        }
        if ($filters['deadlines']) {
            $qb->andWhere(\sprintf(
                'm.insuranceUntil <= :until OR m.inspectionUntil <= :until OR EXISTS (SELECT s.id FROM App\Entity\MachineService s WHERE s.machine = m AND s.nextServiceDate <= :until AND %s)',
                MachineServiceRepository::NOT_SUPERSEDED,
            ))->setParameter('until', $filters['until']);
        }

        return $sort->apply($qb, 'm.name');
    }

    /**
     * Liczba maszyn w każdej kategorii (aktywnych albo wszystkich).
     *
     * @return array<int, int>
     */
    public function countByCategory(bool $withInactive = false): array
    {
        $qb = $this->createQueryBuilder('m')
            ->select('IDENTITY(m.category) AS category, COUNT(m.id) AS machines')
            ->groupBy('m.category');
        if (!$withInactive) {
            $qb->andWhere('m.active = true');
        }

        $counts = [];
        foreach ($qb->getQuery()->getArrayResult() as $row) {
            $counts[(int) $row['category']] = (int) $row['machines'];
        }

        return $counts;
    }

    /**
     * @param list<int> $ids
     *
     * @return array<int, \DateTimeImmutable>
     */
    public function lastServiceDates(array $ids): array
    {
        if ([] === $ids) {
            return [];
        }

        $rows = $this->getEntityManager()->createQueryBuilder()
            ->select('IDENTITY(s.machine) AS machine, MAX(s.date) AS last')
            ->from('App\Entity\MachineService', 's')
            ->where('s.machine IN (:ids)')
            ->setParameter('ids', $ids)
            ->groupBy('s.machine')
            ->getQuery()
            ->getArrayResult();

        $dates = [];
        foreach ($rows as $row) {
            $dates[(int) $row['machine']] = new \DateTimeImmutable((string) $row['last']);
        }

        return $dates;
    }

    /**
     * Aktywne maszyny z ubezpieczeniem albo przeglądem do podanej daty (także po terminie).
     *
     * @param 'insuranceUntil'|'inspectionUntil' $field
     *
     * @return list<Machine>
     */
    public function findDueBefore(string $field, \DateTimeImmutable $until): array
    {
        return $this->createQueryBuilder('m')
            ->where('m.active = true')
            ->andWhere(\sprintf('m.%s <= :until', $field))
            ->setParameter('until', $until)
            ->orderBy('m.'.$field, 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * @return list<Machine>
     */
    public function findActive(): array
    {
        return $this->findBy(['active' => true], ['name' => 'ASC']);
    }

    /**
     * @return list<Machine>
     */
    public function search(string $query, int $limit): array
    {
        return $this->createQueryBuilder('m')
            ->where('LOWER(m.name) LIKE :q OR LOWER(m.registrationNumber) LIKE :q')
            ->setParameter('q', '%'.mb_strtolower($query).'%')
            ->orderBy('m.active', 'DESC')
            ->addOrderBy('m.name', 'ASC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * Liczba wpisów informacji każdej maszyny – do wyboru źródła przy kopiowaniu.
     *
     * @return array<int, int>
     */
    public function countInfoEntries(): array
    {
        $rows = $this->getEntityManager()->createQueryBuilder()
            ->select('IDENTITY(e.machine) AS machine, COUNT(e.id) AS entries')
            ->from(MachineInfoEntry::class, 'e')
            ->groupBy('e.machine')
            ->getQuery()
            ->getArrayResult();

        $counts = [];
        foreach ($rows as $row) {
            $counts[(int) $row['machine']] = (int) $row['entries'];
        }

        return $counts;
    }
}
