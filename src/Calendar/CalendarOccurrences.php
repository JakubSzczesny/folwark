<?php

declare(strict_types=1);

namespace App\Calendar;

use App\Entity\CalendarEvent;
use App\Enum\CalendarRecurrence;

/**
 * Rozwija wydarzenie (także powtarzane) na terminy w zakresie dat.
 * Miesięczne z 31. dnia pomija miesiące bez takiego dnia, roczne z 29 lutego – lata nieprzestępne.
 */
final class CalendarOccurrences
{
    /** Bezpiecznik na wypadek bardzo długiego zakresu. */
    private const int LIMIT = 1000;

    /**
     * Daty rozpoczęcia terminów, które choć jednym dniem zachodzą na zakres [$from, $to].
     *
     * @return list<\DateTimeImmutable>
     */
    public static function starts(CalendarEvent $event, \DateTimeImmutable $from, \DateTimeImmutable $to): array
    {
        $first = $event->getStartDate();
        if (null === $first) {
            return [];
        }

        $from = $from->setTime(0, 0);
        $to = $to->setTime(0, 0);
        // Termin rozpoczęty przed zakresem może jeszcze trwać w jego pierwszym dniu.
        $earliest = $from->modify(\sprintf('-%d days', $event->getSpanDays()));
        $last = $to;
        if ($event->isRecurring() && null !== $event->getRecurrenceUntil() && $event->getRecurrenceUntil() < $last) {
            $last = $event->getRecurrenceUntil();
        }

        return match ($event->getRecurrence()) {
            CalendarRecurrence::None => $first >= $earliest && $first <= $to ? [$first] : [],
            CalendarRecurrence::Daily => self::everyDays($first, 1, $earliest, $last),
            CalendarRecurrence::Weekly => self::everyDays($first, 7, $earliest, $last),
            CalendarRecurrence::Monthly => self::everyMonths($first, 1, $earliest, $last),
            CalendarRecurrence::Yearly => self::everyMonths($first, 12, $earliest, $last),
        };
    }

    /**
     * Najbliższy termin od podanego dnia (włącznie) albo null.
     */
    public static function next(CalendarEvent $event, \DateTimeImmutable $today, int $horizonDays = 400): ?\DateTimeImmutable
    {
        foreach (self::starts($event, $today, $today->modify(\sprintf('+%d days', $horizonDays))) as $start) {
            if ($start >= $today->setTime(0, 0)) {
                return $start;
            }
        }

        return null;
    }

    /**
     * @return list<\DateTimeImmutable>
     */
    private static function everyDays(\DateTimeImmutable $first, int $step, \DateTimeImmutable $earliest, \DateTimeImmutable $last): array
    {
        $skip = 0;
        if ($earliest > $first) {
            $skip = intdiv((int) $first->diff($earliest)->days + $step - 1, $step);
        }

        $dates = [];
        for ($n = $skip; \count($dates) < self::LIMIT; ++$n) {
            $date = $first->modify(\sprintf('+%d days', $n * $step));
            if ($date > $last) {
                break;
            }
            $dates[] = $date;
        }

        return $dates;
    }

    /**
     * @return list<\DateTimeImmutable>
     */
    private static function everyMonths(\DateTimeImmutable $first, int $step, \DateTimeImmutable $earliest, \DateTimeImmutable $last): array
    {
        $day = (int) $first->format('j');
        $firstMonth = $first->modify('first day of this month');

        $skip = 0;
        if ($earliest > $first) {
            $months = ((int) $earliest->format('Y') - (int) $first->format('Y')) * 12 + (int) $earliest->format('n') - (int) $first->format('n');
            $skip = max(0, intdiv($months, $step) - 1);
        }

        $dates = [];
        for ($n = $skip; $n < $skip + self::LIMIT; ++$n) {
            $month = $firstMonth->modify(\sprintf('+%d months', $n * $step));
            if ($month > $last) {
                break;
            }
            if ($day > (int) $month->format('t')) {
                continue;
            }
            $date = $month->setDate((int) $month->format('Y'), (int) $month->format('n'), $day);
            if ($date < $earliest) {
                continue;
            }
            if ($date > $last) {
                break;
            }
            $dates[] = $date;
        }

        return $dates;
    }
}
