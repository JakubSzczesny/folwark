<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\CalendarEvent;
use App\Entity\Contractor;
use App\Entity\User;
use App\Enum\CalendarCategory;
use App\Enum\CalendarRecurrence;
use App\Search\GlobalSearch;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<CalendarEvent>
 */
class CalendarEventRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, CalendarEvent::class);
    }

    /**
     * Wydarzenia, które mogą mieć termin w zakresie dat (powtarzane – do rozwinięcia), widoczne dla użytkownika.
     *
     * @return list<CalendarEvent>
     */
    public function findForRange(\DateTimeImmutable $from, \DateTimeImmutable $to, ?User $user, ?CalendarCategory $category = null, bool $onlyMine = false): array
    {
        $qb = $this->visibleTo($user)
            ->andWhere('e.startDate <= :to')
            ->andWhere('(e.recurrence = :none AND COALESCE(e.endDate, e.startDate) >= :from) OR (e.recurrence != :none AND (e.recurrenceUntil IS NULL OR e.recurrenceUntil >= :loose))')
            ->setParameter('to', $to->format('Y-m-d'))
            ->setParameter('from', $from->format('Y-m-d'))
            // Termin powtarzanego wydarzenia rozpoczęty przed zakresem może w nim jeszcze trwać – dokładnie liczy CalendarOccurrences.
            ->setParameter('loose', $from->modify('-1 year')->format('Y-m-d'))
            ->setParameter('none', CalendarRecurrence::None)
            ->orderBy('e.startDate')
            ->addOrderBy('e.startTime');

        if (null !== $category) {
            $qb->andWhere('e.category = :category')->setParameter('category', $category);
        }
        if ($onlyMine && null !== $user) {
            $qb->andWhere('e.createdBy = :user OR :user MEMBER OF e.participants');
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * Wydarzenia z kontrahentem widoczne dla użytkownika, od najnowszego terminu rozpoczęcia.
     *
     * @return list<CalendarEvent>
     */
    public function findForContractor(Contractor $contractor, ?User $user): array
    {
        return $this->visibleTo($user)
            ->andWhere('e.contractor = :contractor')
            ->setParameter('contractor', $contractor)
            ->orderBy('e.startDate', 'DESC')
            ->addOrderBy('e.startTime', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Wydarzenia z przypomnieniem – do codziennych powiadomień.
     *
     * @return list<CalendarEvent>
     */
    public function findWithReminder(\DateTimeImmutable $today): array
    {
        return $this->createQueryBuilder('e')
            ->leftJoin('e.participants', 'p')
            ->addSelect('p')
            ->where('e.reminderDays IS NOT NULL')
            ->andWhere('e.startDate <= :horizon')
            ->andWhere('(e.recurrence = :none AND e.startDate >= :today) OR (e.recurrence != :none AND (e.recurrenceUntil IS NULL OR e.recurrenceUntil >= :today))')
            ->setParameter('horizon', $today->modify(\sprintf('+%d days', CalendarEvent::MAX_REMINDER_DAYS))->format('Y-m-d'))
            ->setParameter('today', $today->format('Y-m-d'))
            ->setParameter('none', CalendarRecurrence::None)
            ->getQuery()
            ->getResult();
    }

    /**
     * @return list<CalendarEvent>
     */
    public function search(string $query, int $limit, ?User $user): array
    {
        return $this->visibleTo($user)
            ->leftJoin('e.contractor', 'c')
            ->andWhere('LOWER(e.title) LIKE :q OR LOWER(e.location) LIKE :q OR LOWER(c.name) LIKE :q')
            ->setParameter('q', GlobalSearch::likePattern($query))
            ->orderBy('e.startDate', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    private function visibleTo(?User $user): QueryBuilder
    {
        $qb = $this->createQueryBuilder('e');
        if (null === $user) {
            return $qb->where('e.private = false');
        }

        return $qb->where('e.private = false OR e.createdBy = :viewer')->setParameter('viewer', $user);
    }
}
