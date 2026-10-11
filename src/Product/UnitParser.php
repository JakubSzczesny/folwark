<?php

declare(strict_types=1);

namespace App\Product;

use App\Entity\Product;

/**
 * Przeliczanie ilości z faktury na jednostkę podstawową towaru, np. „4 szt. Olej 15W40 5L” → 20 l,
 * „2 t” przy towarze w kg → 2000 kg, „3 op. (opak. 20 szt.)” → 60 szt.
 */
final class UnitParser
{
    private const array ALIASES = [
        'szt' => ['szt', 'sztuka', 'sztuki', 'sztuk', 'pcs', 'pc', 'st'],
        'l' => ['l', 'litr', 'litry', 'litrów', 'litrow', 'ltr', 'lt', 'dm3'],
        'ml' => ['ml'],
        'kg' => ['kg', 'kilogram', 'kilogramy', 'kilogramów'],
        'g' => ['g', 'gr', 'gram'],
        't' => ['t', 'tona', 'tony', 'ton', 'mg'],
        'op' => ['op', 'opak', 'opakowanie', 'opakowania', 'kpl', 'komplet', 'zestaw'],
        'm3' => ['m3', 'm³'],
    ];

    /** Jednostka => [wymiar, ile jednostek bazowych wymiaru]. */
    private const array PHYSICAL = [
        'g' => ['mass', 0.001],
        'kg' => ['mass', 1.0],
        't' => ['mass', 1000.0],
        'ml' => ['volume', 0.001],
        'l' => ['volume', 1.0],
        'm3' => ['volume', 1000.0],
    ];

    /** Jednostki „opakowań”, przy których ilość w środku czytamy z nazwy pozycji. */
    private const array PACKAGES = ['szt', 'op', '', 'worek', 'kanister', 'beczka', 'paleta', 'bigbag'];

    public static function normalize(?string $unit): string
    {
        $unit = mb_strtolower(trim((string) $unit, " \t.,"));
        $unit = str_replace(['.', ' '], '', $unit);
        foreach (self::ALIASES as $symbol => $aliases) {
            if (\in_array($unit, $aliases, true)) {
                return $symbol;
            }
        }

        return $unit;
    }

    /**
     * Ile jednostek podstawowych towaru to jedna jednostka z faktury; null, gdy nie da się tego ustalić.
     */
    public function factor(string $itemName, ?string $itemUnit, Product $product): ?float
    {
        $from = self::normalize($itemUnit);
        $to = self::normalize($product->getUnitSymbol());
        if ('' === $to) {
            return null;
        }
        if ($from === $to) {
            return 1.0;
        }

        foreach ($product->getConversions() as $conversion) {
            if (self::normalize($conversion->getUnit()?->getSymbol()) === $from && null !== $conversion->getFactor()) {
                return (float) $conversion->getFactor();
            }
        }

        $physical = self::physical($from, $to);
        if (null !== $physical) {
            return $physical;
        }

        if (\in_array($from, self::PACKAGES, true)) {
            return $this->packageContent($itemName, $to);
        }

        return null;
    }

    /**
     * Zawartość opakowania z nazwy, np. „Olej 15W40 5L” → 5 l, „Polifoska big bag 500kg” → 500 kg, „opak. 20 szt.” → 20 szt.
     */
    private function packageContent(string $name, string $to): ?float
    {
        if (!preg_match_all('/(\d+(?:[.,]\d+)?)\s*(kg|g|t|l|ml|ltr|litr\w*|szt\w*|m3)(?![a-z0-9])/iu', $name, $matches, \PREG_SET_ORDER)) {
            return null;
        }

        foreach (array_reverse($matches) as [, $value, $unit]) {
            $amount = (float) str_replace(',', '.', $value);
            $unit = self::normalize($unit);
            if ($unit === $to) {
                return $amount;
            }
            $physical = self::physical($unit, $to);
            if (null !== $physical) {
                return $amount * $physical;
            }
        }

        return null;
    }

    private static function physical(string $from, string $to): ?float
    {
        if (!isset(self::PHYSICAL[$from], self::PHYSICAL[$to]) || self::PHYSICAL[$from][0] !== self::PHYSICAL[$to][0]) {
            return null;
        }

        return self::PHYSICAL[$from][1] / self::PHYSICAL[$to][1];
    }
}
