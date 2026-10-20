<?php

declare(strict_types=1);

namespace App\Ui;

use Symfony\Component\HttpFoundation\InputBag;

/**
 * Liczba z parametru adresu, gdzie pusta lub błędna wartość znaczy „brak”. InputBag::getInt() zgłasza wtedy
 * błąd 400, a formularze filtrów wysyłają puste pola wyboru (np. „kontrahent=”).
 */
final class QueryParam
{
    /**
     * @param InputBag<string> $query
     */
    public static function id(InputBag $query, string $key): ?int
    {
        $value = $query->get($key);
        if (!\is_scalar($value) || !ctype_digit((string) $value)) {
            return null;
        }

        return (int) $value ?: null;
    }
}
