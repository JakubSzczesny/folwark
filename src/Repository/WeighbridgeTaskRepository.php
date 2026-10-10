<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\WeighbridgeTask;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<WeighbridgeTask>
 */
class WeighbridgeTaskRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, WeighbridgeTask::class);
    }

    /**
     * @return array<string, list<WeighbridgeTask>> grupa => punkty
     */
    public function findGrouped(): array
    {
        $tasks = $this->createQueryBuilder('t')
            ->leftJoin('t.doneBy', 'u')
            ->addSelect('u')
            ->orderBy('t.position')
            ->addOrderBy('t.id')
            ->getQuery()
            ->getResult();

        $groups = [];
        foreach ($tasks as $task) {
            $groups[$task->getGroupName()][] = $task;
        }

        return $groups;
    }

    /**
     * @return array{done: int, total: int}
     */
    public function progress(): array
    {
        $row = $this->createQueryBuilder('t')
            ->select('COUNT(t.id) AS total', 'SUM(CASE WHEN t.done = true THEN 1 ELSE 0 END) AS done')
            ->getQuery()
            ->getSingleResult();

        return ['done' => (int) $row['done'], 'total' => (int) $row['total']];
    }

    public function nextPosition(): int
    {
        return 1 + (int) $this->createQueryBuilder('t')->select('MAX(t.position)')->getQuery()->getSingleScalarResult();
    }

    /**
     * @return list<string>
     */
    public function groupNames(): array
    {
        $rows = $this->createQueryBuilder('t')
            ->select('t.groupName')
            ->groupBy('t.groupName')
            ->orderBy('MIN(t.position)')
            ->getQuery()
            ->getScalarResult();

        return array_column($rows, 'groupName');
    }
}
