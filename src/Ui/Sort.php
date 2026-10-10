<?php

declare(strict_types=1);

namespace App\Ui;

use Doctrine\ORM\QueryBuilder;
use Symfony\Component\HttpFoundation\Request;

final class Sort
{
    /**
     * @param array<string, string> $allowed klucz w URL => wyrażenie DQL
     */
    private function __construct(
        public readonly string $field,
        public readonly string $direction,
        private readonly array $allowed,
    ) {
    }

    /**
     * @param array<string, string> $allowed
     */
    public static function fromRequest(Request $request, array $allowed, string $defaultField, string $defaultDirection = 'desc'): self
    {
        $field = (string) $request->query->get('sort', $defaultField);
        if (!isset($allowed[$field])) {
            $field = $defaultField;
        }
        $direction = 'asc' === $request->query->get('kierunek', $field === $defaultField ? $defaultDirection : 'asc') ? 'asc' : 'desc';

        return new self($field, $direction, $allowed);
    }

    public function apply(QueryBuilder $qb, ?string $tieBreaker = null): QueryBuilder
    {
        $qb->orderBy($this->allowed[$this->field], strtoupper($this->direction));
        if (null !== $tieBreaker) {
            $qb->addOrderBy($tieBreaker, strtoupper($this->direction));
        }

        return $qb;
    }

    public function directionFor(string $field): string
    {
        if ($field !== $this->field) {
            return 'asc';
        }

        return 'asc' === $this->direction ? 'desc' : 'asc';
    }

    public function isActive(string $field): bool
    {
        return $field === $this->field;
    }
}
