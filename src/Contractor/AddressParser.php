<?php

declare(strict_types=1);

namespace App\Contractor;

final class AddressParser
{
    /**
     * Adres z faktury (AdresL1, AdresL2) albo z Białej listy (jedna linia „ULICA 1, 00-001 MIASTO”).
     *
     * @return array{street: ?string, postalCode: ?string, city: ?string}
     */
    public static function parse(?string $line1, ?string $line2 = null): array
    {
        $line1 = self::clean($line1);
        $line2 = self::clean($line2);

        if (null === $line2 && null !== $line1 && str_contains($line1, ',')) {
            $position = strrpos($line1, ',');
            \assert(false !== $position);
            $line2 = self::clean(substr($line1, $position + 1));
            $line1 = self::clean(substr($line1, 0, $position));
        }

        if (null !== $line2 && 1 === preg_match('/^(\d{2}-\d{3})\s+(.+)$/u', $line2, $m)) {
            return ['street' => $line1, 'postalCode' => $m[1], 'city' => self::title($m[2])];
        }

        if (null !== $line1 && 1 === preg_match('/^(\d{2}-\d{3})\s+(.+)$/u', $line1, $m)) {
            return ['street' => $line2, 'postalCode' => $m[1], 'city' => self::title($m[2])];
        }

        return ['street' => $line1, 'postalCode' => null, 'city' => null !== $line2 ? self::title($line2) : null];
    }

    /**
     * Biała lista zwraca dane wielkimi literami; zamieniamy na zapis z wielkiej litery.
     */
    public static function title(string $value): string
    {
        if (mb_strtoupper($value) !== $value) {
            return $value;
        }

        return mb_convert_case(mb_strtolower($value), \MB_CASE_TITLE);
    }

    private static function clean(?string $value): ?string
    {
        $value = null === $value ? '' : trim(preg_replace('/\s+/u', ' ', $value) ?? '');

        return '' === $value ? null : $value;
    }
}
