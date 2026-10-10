<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Contractor;
use App\Enum\ContractorKind;
use App\Search\GlobalSearch;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Contractor>
 */
class ContractorRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Contractor::class);
    }

    public function createListQuery(?string $text, ?ContractorKind $kind, bool $includeInactive = false): QueryBuilder
    {
        $qb = $this->createQueryBuilder('c')->orderBy('c.name');

        if (null !== $text) {
            $digits = preg_replace('/\D+/', '', $text) ?? '';
            $qb->andWhere('LOWER(c.name) LIKE :q OR LOWER(c.shortName) LIKE :q'.('' !== $digits ? ' OR c.nip LIKE :nip' : ''))
                ->setParameter('q', GlobalSearch::likePattern($text));
            if ('' !== $digits) {
                $qb->setParameter('nip', '%'.$digits.'%');
            }
        }
        if (null !== $kind) {
            $qb->andWhere('c.kind = :kind OR c.kind = :both')
                ->setParameter('kind', $kind)
                ->setParameter('both', ContractorKind::Both);
        }
        if (!$includeInactive) {
            $qb->andWhere('c.active = true');
        }

        return $qb;
    }

    /**
     * @return list<Contractor>
     */
    public function search(string $query, int $limit): array
    {
        return $this->createListQuery($query, null)->setMaxResults($limit)->getQuery()->getResult();
    }
}
