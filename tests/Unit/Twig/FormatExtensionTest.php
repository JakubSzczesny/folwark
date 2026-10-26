<?php

declare(strict_types=1);

namespace App\Tests\Unit\Twig;

use App\Twig\FormatExtension;
use PHPUnit\Framework\TestCase;

final class FormatExtensionTest extends TestCase
{
    private const string NBSP = "\u{00A0}";

    public function testMoneyUsesCommaAndSpaceSeparators(): void
    {
        self::assertSame('1'.self::NBSP.'234'.self::NBSP.'567,89'.self::NBSP.'zł', FormatExtension::money('1234567.891'));
        self::assertSame('12,50'.self::NBSP.'EUR', FormatExtension::money(12.5, 'EUR'));
        self::assertSame('0,00'.self::NBSP.'zł', FormatExtension::money(-0.001));
    }

    public function testQuantityTrimsZeros(): void
    {
        self::assertSame('142,3', FormatExtension::quantity('142.300'));
        self::assertSame('200', FormatExtension::quantity(200));
        self::assertSame('1'.self::NBSP.'500,125', FormatExtension::quantity(1500.125));
    }

    public function testDate(): void
    {
        self::assertSame('03.10.2026', FormatExtension::date(new \DateTimeImmutable('2026-10-03')));
    }

    public function testDayHeading(): void
    {
        $now = new \DateTimeImmutable('2026-10-26 20:00', new \DateTimeZone('Europe/Warsaw'));
        $at = static fn (string $date): \DateTimeImmutable => new \DateTimeImmutable($date, new \DateTimeZone('Europe/Warsaw'));

        self::assertSame('Dziś', FormatExtension::day($at('2026-10-26 00:05'), $now));
        self::assertSame('Wczoraj', FormatExtension::day($at('2026-10-25 23:59'), $now));
        self::assertSame('wtorek, 20 października', FormatExtension::day($at('2026-10-20 09:15'), $now));
        self::assertSame('środa, 31 grudnia 2025', FormatExtension::day($at('2025-12-31 12:00'), $now));
        self::assertSame('', FormatExtension::day(null));
    }

    public function testDeadlineTone(): void
    {
        $today = new \DateTimeImmutable('today');
        self::assertSame('danger', FormatExtension::deadlineTone($today->modify('-1 day'), 3));
        self::assertSame('warning', FormatExtension::deadlineTone($today->modify('+3 days'), 3));
        self::assertNull(FormatExtension::deadlineTone($today->modify('+4 days'), 3));
    }

    public function testPolishPlural(): void
    {
        self::assertSame('faktura', FormatExtension::plural(1, 'faktura', 'faktury', 'faktur'));
        self::assertSame('faktury', FormatExtension::plural(3, 'faktura', 'faktury', 'faktur'));
        self::assertSame('faktur', FormatExtension::plural(12, 'faktura', 'faktury', 'faktur'));
        self::assertSame('faktury', FormatExtension::plural(22, 'faktura', 'faktury', 'faktur'));
        self::assertSame('faktur', FormatExtension::plural(25, 'faktura', 'faktury', 'faktur'));
    }

    public function testDaysLabel(): void
    {
        self::assertSame('dziś', FormatExtension::daysLabel(0));
        self::assertSame('za 14 dni', FormatExtension::daysLabel(14));
        self::assertSame('2 dni po terminie', FormatExtension::daysLabel(-2));
    }
}
