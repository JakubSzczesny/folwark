<?php

declare(strict_types=1);

namespace App\Tree;

/**
 * Węzeł drzewa kategorii z liczbą wpisów jednego obiektu (maszyny, towaru).
 *
 * @template T of TreeCategory
 */
final class TreeNode
{
    /**
     * @param T                 $category
     * @param list<TreeNode<T>> $children
     */
    public function __construct(
        public readonly TreeCategory $category,
        public readonly int $depth,
        public readonly int $count,
        public int $total = 0,
        public array $children = [],
        public bool $visible = true,
    ) {
    }

    public function id(): int
    {
        return (int) $this->category->getId();
    }

    /**
     * @return list<int> id tej kategorii i wszystkich podkategorii
     */
    public function ids(): array
    {
        $ids = [$this->id()];
        foreach ($this->children as $child) {
            array_push($ids, ...$child->ids());
        }

        return $ids;
    }

    /**
     * @return list<TreeNode<T>> ten węzeł i wszystkie potomne, w kolejności drzewa
     */
    public function flatten(): array
    {
        $flat = [$this];
        foreach ($this->children as $child) {
            array_push($flat, ...$child->flatten());
        }

        return $flat;
    }
}
