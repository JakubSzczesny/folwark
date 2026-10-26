<?php

declare(strict_types=1);

namespace App\Ui;

use Doctrine\ORM\QueryBuilder;
use Doctrine\ORM\Tools\Pagination\Paginator;

/**
 * @template T
 */
final class Pager
{
    public const int PER_PAGE = 50;

    /**
     * @param list<T> $items
     */
    private function __construct(
        public readonly array $items,
        public readonly int $total,
        public readonly int $page,
        public readonly int $perPage,
    ) {
    }

    /**
     * @return self<mixed>
     */
    public static function fromQuery(QueryBuilder $qb, int $page, int $perPage = self::PER_PAGE, bool $fetchJoin = true): self
    {
        $page = max(1, $page);
        $qb->setFirstResult(($page - 1) * $perPage)->setMaxResults($perPage);
        $paginator = new Paginator($qb, $fetchJoin);
        $total = \count($paginator);

        $pages = max(1, (int) ceil($total / $perPage));
        if ($page > $pages) {
            return self::fromQuery($qb, $pages, $perPage, $fetchJoin);
        }

        return new self(iterator_to_array($paginator, false), $total, $page, $perPage);
    }

    /**
     * @template I
     *
     * @param list<I> $items
     *
     * @return self<I>
     */
    public static function fromArray(array $items, int $page, int $perPage = self::PER_PAGE): self
    {
        $total = \count($items);
        $pages = max(1, (int) ceil($total / $perPage));
        $page = min(max(1, $page), $pages);

        return new self(\array_slice($items, ($page - 1) * $perPage, $perPage), $total, $page, $perPage);
    }

    /**
     * Ta sama strona z innymi elementami – np. identyfikatory zamienione na wczytane obiekty.
     *
     * @template I
     *
     * @param list<I> $items
     *
     * @return self<I>
     */
    public function withItems(array $items): self
    {
        return new self($items, $this->total, $this->page, $this->perPage);
    }

    public function pages(): int
    {
        return max(1, (int) ceil($this->total / $this->perPage));
    }

    public function hasPrevious(): bool
    {
        return $this->page > 1;
    }

    public function hasNext(): bool
    {
        return $this->page < $this->pages();
    }

    public function firstIndex(): int
    {
        return 0 === $this->total ? 0 : ($this->page - 1) * $this->perPage + 1;
    }

    public function lastIndex(): int
    {
        return min($this->total, $this->page * $this->perPage);
    }
}
