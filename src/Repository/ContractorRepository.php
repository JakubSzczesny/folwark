<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Contractor;
use App\Enum\ContractorKind;
use App\Search\GlobalSearch;
use App\Ui\Sort;
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

    /**
     * Kolumny sortowania listy; sumy faktur liczone za podany rok, kwoty tylko dla uprawnionych.
     *
     * @return array<string, string>
     */
    public static function sortFields(int $year, bool $amounts): array
    {
        $sum = static fn (string $kind, string $alias): string => \sprintf(
            "(SELECT SUM(%1\$s.net) FROM App\Entity\Invoice %1\$s WHERE %1\$s.contractor = c AND %1\$s.kind = '%2\$s' AND %1\$s.status != 'skipped' AND %1\$s.issueDate BETWEEN '%3\$d-01-01' AND '%3\$d-12-31')",
            $alias, $kind, $year,
        );

        return [
            'nazwa' => 'c.name',
            'nip' => 'c.nip',
            'miejscowosc' => 'c.city',
        ] + ($amounts ? [
            'koszty' => $sum('cost', 'sort_k'),
            'przychody' => $sum('income', 'sort_p'),
        ] : []) + [
            'faktura' => '(SELECT MAX(sort_f.issueDate) FROM App\Entity\Invoice sort_f WHERE sort_f.contractor = c)',
            'umowy' => "(SELECT COUNT(sort_u.id) FROM App\Entity\Contract sort_u WHERE sort_u.contractor = c AND sort_u.status = 'active')",
        ];
    }

    public function createListQuery(?string $text, ?ContractorKind $kind, bool $includeInactive = false, ?Sort $sort = null): QueryBuilder
    {
        $qb = $this->createQueryBuilder('c')->orderBy('c.name');
        $sort?->apply($qb, 'c.name', 'c.id');

        if (null !== $text) {
            $digits = preg_replace('/\D+/', '', $text) ?? '';
            $qb->andWhere('LOWER(c.name) LIKE :q OR LOWER(c.shortName) LIKE :q OR LOWER(c.email) LIKE :q'.('' !== $digits ? ' OR c.nip LIKE :nip' : ''))
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

    /**
     * Kontrahenci z wpisanym adresem e-mail – do szukania w ich korespondencji.
     *
     * @return list<Contractor>
     */
    public function findWithEmail(): array
    {
        return $this->createQueryBuilder('c')
            ->andWhere("c.email IS NOT NULL AND c.email != ''")
            ->orderBy('c.active', 'DESC')
            ->addOrderBy('c.name')
            ->getQuery()->getResult();
    }
}
