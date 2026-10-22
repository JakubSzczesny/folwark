<?php

declare(strict_types=1);

namespace App\Ui;

use Doctrine\ORM\QueryBuilder;
use Symfony\Component\HttpFoundation\Request;

/**
 * Sortowanie list po jednej albo dwóch kolumnach, zapisane w adresie: sort/kierunek i sort2/kierunek2.
 * Kliknięcie nagłówka sortuje tylko po tej kolumnie (drugie kliknięcie odwraca kierunek),
 * Shift + kliknięcie dokłada kolumnę jako drugie kryterium.
 */
final class Sort
{
    public const int MAX = 2;

    /**
     * @param list<array{string, string}> $keys    [pole, kierunek] w kolejności ważności
     * @param array<string, string>       $allowed klucz w URL => wyrażenie DQL
     * @param list<string>                $descFirst pola, które po pierwszym kliknięciu sortują malejąco (daty, kwoty)
     */
    private function __construct(
        private readonly array $keys,
        private readonly array $allowed,
        private readonly string $defaultField,
        private readonly string $defaultDirection,
        private readonly array $descFirst,
    ) {
    }

    /**
     * @param array<string, string> $allowed
     * @param list<string>          $descFirst
     */
    public static function fromRequest(Request $request, array $allowed, string $defaultField, string $defaultDirection = 'desc', array $descFirst = []): self
    {
        $query = $request->query;
        $keys = [];
        foreach (['' => 'kierunek', '2' => 'kierunek2'] as $suffix => $directionKey) {
            $field = $query->get('sort'.$suffix);
            if (!\is_string($field) || !isset($allowed[$field]) || \in_array($field, array_column($keys, 0), true)) {
                break;
            }
            $direction = $query->get($directionKey);
            $keys[] = [$field, \in_array($direction, ['asc', 'desc'], true) ? $direction : self::initial($field, $defaultField, $defaultDirection, $descFirst)];
        }

        return new self($keys ?: [[$defaultField, $defaultDirection]], $allowed, $defaultField, $defaultDirection, $descFirst);
    }

    public function apply(QueryBuilder $qb, string ...$tieBreakers): QueryBuilder
    {
        $qb->resetDQLPart('orderBy');
        foreach ($this->keys as $i => [$field, $direction]) {
            $expression = $this->allowed[$field];
            // Podzapytań i wyrażeń CASE nie da się wpisać wprost do ORDER BY w DQL – sortujemy po ukrytej kolumnie.
            if (!preg_match('/^\w+\.\w+$/', $expression)) {
                $alias = 'sort_'.$i;
                $qb->addSelect(\sprintf('%s AS HIDDEN %s', $expression, $alias));
                $expression = $alias;
            }
            $qb->addOrderBy($expression, strtoupper($direction));
        }
        foreach ($tieBreakers as $tieBreaker) {
            $qb->addOrderBy($tieBreaker, strtoupper($this->direction()));
        }

        return $qb;
    }

    public function field(): string
    {
        return $this->keys[0][0];
    }

    public function direction(): string
    {
        return $this->keys[0][1];
    }

    public function isActive(string $field): bool
    {
        return null !== $this->position($field);
    }

    /**
     * Miejsce kolumny w sortowaniu (1, 2) albo null, gdy po niej nie sortujemy.
     */
    public function position(string $field): ?int
    {
        $index = array_search($field, array_column($this->keys, 0), true);

        return false === $index ? null : $index + 1;
    }

    public function directionOf(string $field): ?string
    {
        $position = $this->position($field);

        return null !== $position ? $this->keys[$position - 1][1] : null;
    }

    public function isMulti(): bool
    {
        return \count($this->keys) > 1;
    }

    /**
     * Parametry adresu po kliknięciu nagłówka; $add – z Shiftem, jako dodatkowe kryterium.
     *
     * @return array<string, ?string>
     */
    public function queryFor(string $field, bool $add = false): array
    {
        $keys = $this->keys;
        $position = $this->position($field);

        if (1 === $position || ($add && null !== $position)) {
            $keys[$position - 1][1] = 'asc' === $keys[$position - 1][1] ? 'desc' : 'asc';
        } elseif ($add) {
            $keys = [$keys[0], [$field, $this->initialFor($field)]];
        } else {
            $keys = [[$field, $this->initialFor($field)]];
        }

        return self::encode($keys, $this->defaultField, $this->defaultDirection);
    }

    /**
     * Bieżące sortowanie jako parametry adresu (puste, gdy domyślne).
     *
     * @return array<string, string>
     */
    public function toQuery(): array
    {
        return array_filter(self::encode($this->keys, $this->defaultField, $this->defaultDirection), static fn (?string $v): bool => null !== $v);
    }

    /**
     * @param list<array{string, string}> $keys
     *
     * @return array<string, ?string>
     */
    private static function encode(array $keys, string $defaultField, string $defaultDirection): array
    {
        if ([[$defaultField, $defaultDirection]] === $keys) {
            return ['sort' => null, 'kierunek' => null, 'sort2' => null, 'kierunek2' => null];
        }

        return [
            'sort' => $keys[0][0],
            'kierunek' => $keys[0][1],
            'sort2' => $keys[1][0] ?? null,
            'kierunek2' => $keys[1][1] ?? null,
        ];
    }

    private function initialFor(string $field): string
    {
        return self::initial($field, $this->defaultField, $this->defaultDirection, $this->descFirst);
    }

    /**
     * @param list<string> $descFirst
     */
    private static function initial(string $field, string $defaultField, string $defaultDirection, array $descFirst): string
    {
        if ($field === $defaultField) {
            return $defaultDirection;
        }

        return \in_array($field, $descFirst, true) ? 'desc' : 'asc';
    }
}
