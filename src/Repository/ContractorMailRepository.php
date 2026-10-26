<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Contractor;
use App\Entity\ContractorMail;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ContractorMail>
 */
class ContractorMailRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ContractorMail::class);
    }

    public function createForContractorQuery(Contractor $contractor): QueryBuilder
    {
        return $this->createQueryBuilder('m')
            ->andWhere('m.contractor = :contractor')
            ->setParameter('contractor', $contractor)
            ->orderBy('m.sentAt', 'DESC')
            ->addOrderBy('m.uid', 'DESC');
    }

    public function countForContractor(Contractor $contractor): int
    {
        return (int) $this->createQueryBuilder('m')
            ->select('COUNT(m.id)')
            ->andWhere('m.contractor = :contractor')
            ->setParameter('contractor', $contractor)
            ->getQuery()->getSingleScalarResult();
    }

    /**
     * UID-y wiadomości już w indeksie bieżącego gospodarstwa.
     *
     * @return list<int>
     */
    public function indexedUids(): array
    {
        return array_values(array_map('intval', $this->createQueryBuilder('m')
            ->select('DISTINCT m.uid')
            ->getQuery()->getSingleColumnResult()));
    }

    /**
     * Gospodarstwo podajemy jawnie – filtr gospodarstwa nie zawsze obejmuje DELETE w DQL.
     *
     * @param list<int> $uids
     */
    public function deleteUids(int $farmId, array $uids): void
    {
        foreach (array_chunk($uids, 500) as $chunk) {
            $this->createQueryBuilder('m')->delete()
                ->andWhere('IDENTITY(m.farm) = :farm AND m.uid IN (:uids)')
                ->setParameter('farm', $farmId)
                ->setParameter('uids', $chunk)
                ->getQuery()->execute();
        }
    }

    public function deleteAll(int $farmId): void
    {
        $this->createQueryBuilder('m')->delete()->andWhere('IDENTITY(m.farm) = :farm')->setParameter('farm', $farmId)->getQuery()->execute();
    }
}
