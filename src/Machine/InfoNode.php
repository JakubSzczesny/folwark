<?php

declare(strict_types=1);

namespace App\Machine;

use App\Entity\MachineInfoCategory;

/**
 * Węzeł drzewa kategorii informacji z liczbą wpisów konkretnej maszyny.
 */
final class InfoNode
{
    /**
     * @param list<InfoNode> $children
     */
    public function __construct(
        public readonly MachineInfoCategory $category,
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
}
