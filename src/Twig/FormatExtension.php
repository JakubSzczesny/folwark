<?php

declare(strict_types=1);

namespace App\Twig;

use Twig\Attribute\AsTwigFilter;
use Twig\Attribute\AsTwigFunction;

/**
 * Polski zapis liczb i dat: przecinek dziesiętny, spacja tysięcy, DD.MM.RRRR.
 */
final class FormatExtension
{
    public const string THOUSANDS = "\u{00A0}";

    private const array MONTHS = [
        1 => 'styczeń', 'luty', 'marzec', 'kwiecień', 'maj', 'czerwiec',
        'lipiec', 'sierpień', 'wrzesień', 'październik', 'listopad', 'grudzień',
    ];

    private const array MONTHS_SHORT = [
        1 => 'sty', 'lut', 'mar', 'kwi', 'maj', 'cze', 'lip', 'sie', 'wrz', 'paź', 'lis', 'gru',
    ];

    /** Dopełniacz – „20 października”. */
    private const array MONTHS_GENITIVE = [
        1 => 'stycznia', 'lutego', 'marca', 'kwietnia', 'maja', 'czerwca',
        'lipca', 'sierpnia', 'września', 'października', 'listopada', 'grudnia',
    ];

    private const array WEEKDAYS = [1 => 'poniedziałek', 'wtorek', 'środa', 'czwartek', 'piątek', 'sobota', 'niedziela'];


    public static function number(float|int|string|null $value, int $decimals = 2): string
    {
        if (null === $value || '' === $value) {
            return '';
        }

        $formatted = number_format((float) $value, $decimals, ',', self::THOUSANDS);

        return '-0' === substr($formatted, 0, 2) && 0.0 === round((float) $value, $decimals) ? substr($formatted, 1) : $formatted;
    }

    #[AsTwigFilter('amount')]
    public static function amount(float|int|string|null $value, int $decimals = 2): string
    {
        return self::number($value, $decimals);
    }

    #[AsTwigFilter('money')]
    public static function money(float|int|string|null $value, ?string $currency = 'PLN'): string
    {
        if (null === $value || '' === $value) {
            return '';
        }

        $suffix = null === $currency || 'PLN' === $currency ? 'zł' : $currency;

        return self::number($value).self::THOUSANDS.$suffix;
    }

    #[AsTwigFilter('qty')]
    public static function quantity(float|int|string|null $value, int $maxDecimals = 3): string
    {
        if (null === $value || '' === $value) {
            return '';
        }

        $formatted = self::number($value, $maxDecimals);
        if (str_contains($formatted, ',')) {
            $formatted = rtrim(rtrim($formatted, '0'), ',');
        }

        return $formatted;
    }

    #[AsTwigFilter('percent')]
    public static function percent(float|int|null $value, int $decimals = 0): string
    {
        return null === $value ? '' : self::number($value, $decimals).self::THOUSANDS.'%';
    }

    #[AsTwigFilter('pl_date')]
    public static function date(?\DateTimeInterface $date): string
    {
        return $date?->format('d.m.Y') ?? '';
    }

    /**
     * Nagłówek dnia na liście: „Dziś”, „Wczoraj”, „wtorek, 20 października” (z rokiem, gdy nie bieżący).
     */
    #[AsTwigFilter('pl_day')]
    public static function day(?\DateTimeInterface $date, ?\DateTimeInterface $now = null): string
    {
        if (null === $date) {
            return '';
        }

        $date = self::local($date);
        $today = self::local($now ?? new \DateTimeImmutable())->setTime(0, 0);
        $days = (int) $today->diff($date->setTime(0, 0))->format('%r%a');

        return match (true) {
            0 === $days => 'Dziś',
            -1 === $days => 'Wczoraj',
            default => \sprintf('%s, %d %s', self::WEEKDAYS[(int) $date->format('N')], (int) $date->format('j'), self::MONTHS_GENITIVE[(int) $date->format('n')])
                .($date->format('Y') !== $today->format('Y') ? ' '.$date->format('Y') : ''),
        };
    }

    #[AsTwigFilter('pl_datetime')]

    public static function dateTime(?\DateTimeInterface $date): string
    {
        return null === $date ? '' : self::local($date)->format('d.m.Y H:i');
    }

    #[AsTwigFilter('pl_month')]
    public static function month(?\DateTimeInterface $date, bool $short = false): string
    {
        if (null === $date) {
            return '';
        }

        $name = ($short ? self::MONTHS_SHORT : self::MONTHS)[(int) $date->format('n')];

        return $short ? $name.' '.$date->format('y') : $name.' '.$date->format('Y');
    }

    #[AsTwigFunction('month_name')]
    public static function monthName(int $month): string
    {
        return self::MONTHS[$month] ?? '';
    }

    #[AsTwigFunction('days_left')]
    public static function daysLeft(?\DateTimeInterface $date, ?\DateTimeInterface $today = null): ?int
    {
        if (null === $date) {
            return null;
        }

        $today = \DateTimeImmutable::createFromInterface($today ?? new \DateTimeImmutable('today'))->setTime(0, 0);
        $day = \DateTimeImmutable::createFromInterface($date)->setTime(0, 0);

        return (int) $today->diff($day)->format('%r%a');
    }

    /**
     * Kolor terminu: błąd po terminie, ostrzeżenie gdy zostało ≤ $warnDays dni.
     */
    #[AsTwigFunction('deadline_tone')]
    public static function deadlineTone(?\DateTimeInterface $date, int $warnDays = 30): ?string
    {
        $days = self::daysLeft($date);

        return match (true) {
            null === $days => null,
            $days < 0 => 'danger',
            $days <= $warnDays => 'warning',
            default => null,
        };
    }

    #[AsTwigFunction('days_label')]
    public static function daysLabel(?int $days): string
    {
        return match (true) {
            null === $days => '',
            0 === $days => 'dziś',
            1 === $days => 'jutro',
            -1 === $days => 'wczoraj',
            $days > 0 => 'za '.$days.' '.self::plural($days, 'dzień', 'dni', 'dni'),
            default => abs($days).' '.self::plural(abs($days), 'dzień', 'dni', 'dni').' po terminie',
        };
    }

    #[AsTwigFilter('plural')]
    public static function plural(int $count, string $one, string $few, string $many): string
    {
        if (1 === $count) {
            return $one;
        }

        $mod10 = $count % 10;
        $mod100 = $count % 100;

        return $mod10 >= 2 && $mod10 <= 4 && ($mod100 < 12 || $mod100 > 14) ? $few : $many;
    }

    private static function local(\DateTimeInterface $date): \DateTimeImmutable
    {
        return \DateTimeImmutable::createFromInterface($date)->setTimezone(new \DateTimeZone('Europe/Warsaw'));
    }
}
