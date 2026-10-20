<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Note;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Note>
 */
class NoteRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Note::class);
    }

    /**
     * @return list<Note> od najnowszej
     */
    public function findFor(string $type, int $id): array
    {
        return $this->createQueryBuilder('n')
            ->leftJoin('n.author', 'a')
            ->addSelect('a')
            ->where('n.subjectType = :type')
            ->andWhere('n.subjectId = :id')
            ->setParameter('type', $type)
            ->setParameter('id', $id)
            ->orderBy('n.createdAt', 'DESC')
            ->addOrderBy('n.id', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function countFor(string $type, int $id): int
    {
        return $this->count(['subjectType' => $type, 'subjectId' => $id]);
    }

    /**
     * @param list<int> $ids
     */
    public function deleteFor(string $type, array $ids): void
    {
        if ([] === $ids) {
            return;
        }

        $this->createQueryBuilder('n')
            ->delete()
            ->where('n.subjectType = :type')
            ->andWhere('n.subjectId IN (:ids)')
            ->setParameter('type', $type)
            ->setParameter('ids', $ids)
            ->getQuery()
            ->execute();
    }
}
