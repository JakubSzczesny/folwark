<?php

declare(strict_types=1);

namespace App\Util;

final class Nip
{
    private const array WEIGHTS = [6, 5, 7, 2, 3, 4, 5, 6, 7];

    /**
     * Same cyfry; prefiks "PL" i separatory są usuwane. Zwraca null, gdy nie ma 10 cyfr.
     */
    public static function normalize(?string $value): ?string
    {
        if (null === $value) {
            return null;
        }

        $value = strtoupper(trim($value));
        if (str_starts_with($value, 'PL')) {
            $value = substr($value, 2);
        }

        $digits = preg_replace('/\D+/', '', $value) ?? '';

        return 10 === \strlen($digits) ? $digits : null;
    }

    public static function isValid(?string $value): bool
    {
        $nip = self::normalize($value);
        if (null === $nip) {
            return false;
        }

        $sum = 0;
        foreach (self::WEIGHTS as $i => $weight) {
            $sum += $weight * (int) $nip[$i];
        }

        $control = $sum % 11;

        return 10 !== $control && $control === (int) $nip[9];
    }

    public static function format(?string $nip): string
    {
        if (null === $nip || 10 !== \strlen($nip)) {
            return (string) $nip;
        }

        return substr($nip, 0, 3).'-'.substr($nip, 3, 3).'-'.substr($nip, 6, 2).'-'.substr($nip, 8, 2);
    }
}
