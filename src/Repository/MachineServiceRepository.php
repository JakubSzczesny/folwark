<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Machine;
use App\Entity\MachineService;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<MachineService>
 */
class MachineServiceRepository extends ServiceEntityRepository
{
    /**
     * Zaplanowany termin z wpisu „s” obowiązuje, dopóki nie ma późniejszego wpisu
     * tego samego rodzaju dla tej maszyny (np. kolejnej wymiany oleju).
     */
    public const string NOT_SUPERSEDED = 'NOT EXISTS (SELECT s2.id FROM App\Entity\MachineService s2 WHERE s2.machine = s.machine AND s2.type = s.type AND (s2.date > s.date OR (s2.date = s.date AND s2.id > s.id)))';

    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, MachineService::class);
    }

    /**
     * @return list<MachineService>
     */
    public function findForMachine(Machine $machine): array
    {
        return $this->createQueryBuilder('s')
            ->addSelect('t', 'c', 'i')
            ->leftJoin('s.type', 't')
            ->leftJoin('s.contractor', 'c')
            ->leftJoin('s.invoice', 'i')
            ->where('s.machine = :machine')
            ->setParameter('machine', $machine)
            ->orderBy('s.date', 'DESC')
            ->addOrderBy('s.id', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Aktualne plany kolejnego serwisu (wg daty lub licznika) aktywnych maszyn.
     *
     * @return list<MachineService>
     */
    public function findOpenPlans(?Machine $machine = null, ?\DateTimeImmutable $until = null): array
    {
        $qb = $this->createQueryBuilder('s')
            ->addSelect('m', 't')
            ->join('s.machine', 'm')
            ->leftJoin('s.type', 't')
            ->where('m.active = true')
            ->andWhere('s.nextServiceDate IS NOT NULL OR s.nextServiceMeter IS NOT NULL')
            ->andWhere(self::NOT_SUPERSEDED)
            ->orderBy('s.nextServiceDate', 'ASC');

        if (null !== $machine) {
            $qb->andWhere('s.machine = :machine')->setParameter('machine', $machine);
        }
        if (null !== $until) {
            $qb->andWhere('s.nextServiceDate <= :until')->setParameter('until', $until);
        }

        return $qb->getQuery()->getResult();
    }

    public function findLastForMachine(Machine $machine): ?MachineService
    {
        return $this->createQueryBuilder('s')
            ->where('s.machine = :machine')
            ->setParameter('machine', $machine)
            ->orderBy('s.date', 'DESC')
            ->addOrderBy('s.id', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * @return list<MachineService>
     */
    public function findWithInvoice(Machine $machine): array
    {
        return $this->createQueryBuilder('s')
            ->addSelect('i')
            ->join('s.invoice', 'i')
            ->where('s.machine = :machine')
            ->setParameter('machine', $machine)
            ->getQuery()
            ->getResult();
    }
}
