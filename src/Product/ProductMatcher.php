<?php

declare(strict_types=1);

namespace App\Product;

use App\Entity\Product;

/**
 * Propozycja towaru dla pozycji faktury na podstawie podobieństwa nazw.
 */
final class ProductMatcher
{
    private const float THRESHOLD = 0.34;

    /**
     * Klucz nazwy pozycji do zapamiętanych mapowań: małe litery, bez znaków interpunkcyjnych.
     */
    public static function key(string $name): string
    {
        $key = mb_strtolower(trim($name));
        $key = preg_replace('/[^\p{L}\p{N}]+/u', ' ', $key) ?? $key;

        return mb_substr(trim(preg_replace('/\s+/u', ' ', $key) ?? $key), 0, 255);
    }

    /**
     * @param list<Product> $products
     */
    public function suggest(string $itemName, array $products): ?Product
    {
        $itemTokens = self::tokens($itemName);
        $compact = str_replace(' ', '', self::key($itemName));
        $best = null;
        $bestScore = 0.0;

        foreach ($products as $product) {
            $score = self::similarity($itemTokens, self::tokens($product->getName()));
            $catalog = str_replace(' ', '', self::key((string) $product->getCatalogNumber()));
            if (mb_strlen($catalog) >= 4 && str_contains($compact, $catalog)) {
                $score += 0.6;
            }
            if ($score > $bestScore) {
                $best = $product;
                $bestScore = $score;
            }
        }

        return $bestScore >= self::THRESHOLD ? $best : null;
    }

    /**
     * Podobieństwo zbiorów słów (Jaccard), słowa liczą się także, gdy jedno zaczyna drugie („pszen” ~ „pszenica”).
     *
     * @param list<string> $a
     * @param list<string> $b
     */
    private static function similarity(array $a, array $b): float
    {
        if ([] === $a || [] === $b) {
            return 0.0;
        }

        $common = 0;
        foreach ($b as $word) {
            foreach ($a as $other) {
                if ($word === $other || (mb_strlen($word) >= 4 && mb_strlen($other) >= 4 && (str_starts_with($word, $other) || str_starts_with($other, $word)))) {
                    ++$common;
                    break;
                }
            }
        }

        return $common / (\count($a) + \count($b) - $common);
    }

    /**
     * @return list<string>
     */
    private static function tokens(string $name): array
    {
        $words = array_filter(explode(' ', self::key($name)), static fn (string $w): bool => mb_strlen($w) >= 2 && !preg_match('/^\d+([.,]\d+)?(kg|l|t|g|ml|szt)?$/u', $w));

        return array_values(array_unique($words));
    }
}
