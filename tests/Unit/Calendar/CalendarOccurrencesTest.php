<?php

declare(strict_types=1);

namespace App\Tests\Unit\Calendar;

use App\Calendar\CalendarOccurrences;
use App\Entity\CalendarEvent;
use App\Enum\CalendarRecurrence;
use PHPUnit\Framework\TestCase;

final class CalendarOccurrencesTest extends TestCase
{
    public function testSingleEventInsideAndOutsideRange(): void
    {
        $event = self::event('2026-10-15');

        self::assertSame(['2026-10-15'], self::starts($event, '2026-10-01', '2026-10-31'));
        self::assertSame([], self::starts($event, '2026-11-01', '2026-11-30'));
    }

    public function testMultiDayEventStartedBeforeRangeIsIncluded(): void
    {
        $event = self::event('2026-09-29')->setEndDate(new \DateTimeImmutable('2026-10-02'));

        self::assertSame(['2026-09-29'], self::starts($event, '2026-10-01', '2026-10-31'));
        self::assertSame(3, $event->getSpanDays());
    }

    public function testWeeklyWithEndDate(): void
    {
        $event = self::event('2026-10-05', CalendarRecurrence::Weekly)->setRecurrenceUntil(new \DateTimeImmutable('2026-10-26'));

        self::assertSame(['2026-10-19', '2026-10-26'], self::starts($event, '2026-10-15', '2026-11-30'));
    }

    public function testDailyStartingLongAgoJumpsToRange(): void
    {
        $event = self::event('2020-01-01', CalendarRecurrence::Daily);

        $starts = self::starts($event, '2026-10-01', '2026-10-07');
        self::assertCount(7, $starts);
        self::assertSame('2026-10-01', $starts[0]);
    }

    public function testMonthlyOn31stSkipsShortMonths(): void
    {
        $event = self::event('2026-01-31', CalendarRecurrence::Monthly);

        self::assertSame(['2026-01-31', '2026-03-31', '2026-05-31'], self::starts($event, '2026-01-01', '2026-06-15'));
    }

    public function testYearlyOnLeapDay(): void
    {
        $event = self::event('2024-02-29', CalendarRecurrence::Yearly);

        self::assertSame(['2028-02-29'], self::starts($event, '2025-01-01', '2028-12-31'));
    }

    public function testNextOccurrence(): void
    {
        $event = self::event('2026-01-10', CalendarRecurrence::Monthly);

        self::assertSame('2026-11-10', CalendarOccurrences::next($event, new \DateTimeImmutable('2026-10-20'))?->format('Y-m-d'));
        self::assertSame('2026-10-10', CalendarOccurrences::next($event, new \DateTimeImmutable('2026-10-10'))?->format('Y-m-d'));
    }

    private static function event(string $start, CalendarRecurrence $recurrence = CalendarRecurrence::None): CalendarEvent
    {
        return (new CalendarEvent(null, new \DateTimeImmutable($start)))->setTitle('Test')->setRecurrence($recurrence);
    }

    /**
     * @return list<string>
     */
    private static function starts(CalendarEvent $event, string $from, string $to): array
    {
        return array_map(
            static fn (\DateTimeImmutable $d): string => $d->format('Y-m-d'),
            CalendarOccurrences::starts($event, new \DateTimeImmutable($from), new \DateTimeImmutable($to)),
        );
    }
}
