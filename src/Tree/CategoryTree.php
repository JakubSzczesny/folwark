<?php

declare(strict_types=1);

namespace App\Tree;

/**
 * Drzewo kategorii z liczbą wpisów jednego obiektu. Puste kategorie są ukryte, chyba że
 * $showAll albo są wybrane (lub są przodkiem wybranej). Bez wyboru wybieramy pierwszą niepustą.
 */
final class CategoryTree
{
    /**
     * @template T of TreeCategory
     *
     * @param list<T>         $categories posortowane wg pozycji
     * @param array<int, int> $counts     id kategorii => liczba wpisów
     *
     * @return array{nodes: list<TreeNode<T>>, selected: ?TreeNode<T>, hidden: int}
     */
    public static function build(array $categories, array $counts, ?int $selectedId = null, bool $showAll = false): array
    {
        $byParent = [];
        foreach ($categories as $category) {
            $byParent[$category->getParent()?->getId() ?? 0][] = $category;
        }

        $nodes = self::level($byParent, 0, 0, $counts);
        $flat = self::flatten($nodes);

        $selected = null;
        foreach ($flat as $node) {
            if ($node->id() === $selectedId) {
                $selected = $node;
            }
        }
        if (null === $selected) {
            foreach ($flat as $node) {
                if ($node->total > 0) {
                    $selected = $node;
                    break;
                }
            }
        }
        $selected ??= $flat[0] ?? null;

        $keep = [];
        for ($current = $selected?->category; null !== $current; $current = $current->getParent()) {
            $keep[] = (int) $current->getId();
        }

        $hidden = 0;
        foreach ($flat as $node) {
            $node->visible = $showAll || $node->total > 0 || \in_array($node->id(), $keep, true);
            $hidden += $node->visible ? 0 : 1;
        }

        return ['nodes' => $nodes, 'selected' => $selected, 'hidden' => $hidden];
    }

    /**
     * Kategorie w kolejności drzewa (rodzic, potem jego podkategorie) – do list wyboru.
     *
     * @template T of TreeCategory
     *
     * @param list<T> $categories posortowane wg pozycji
     *
     * @return list<T>
     */
    public static function ordered(array $categories): array
    {
        return array_map(static fn (TreeNode $node): TreeCategory => $node->category, self::flatten(self::build($categories, [], null, true)['nodes']));
    }

    /**
     * Nowa kolejność rodzeństwa po przeciągnięciu. Kategorie spoza listy trafiają na koniec.
     *
     * @param list<TreeCategory> $siblings
     * @param list<int>          $ids
     */
    public static function reorder(array $siblings, array $ids): void
    {
        $byId = [];
        foreach ($siblings as $category) {
            $byId[(int) $category->getId()] = $category;
        }

        $position = 0;
        foreach ($ids as $id) {
            if (isset($byId[$id])) {
                $byId[$id]->setPosition($position++);
                unset($byId[$id]);
            }
        }
        foreach ($byId as $category) {
            $category->setPosition($position++);
        }
    }

    /**
     * @template T of TreeCategory
     *
     * @param list<TreeNode<T>> $nodes
     *
     * @return list<TreeNode<T>>
     */
    public static function flatten(array $nodes): array
    {
        $flat = [];
        foreach ($nodes as $node) {
            array_push($flat, ...$node->flatten());
        }

        return $flat;
    }

    /**
     * @template T of TreeCategory
     *
     * @param array<int, list<T>> $byParent
     * @param array<int, int>     $counts
     *
     * @return list<TreeNode<T>>
     */
    private static function level(array $byParent, int $parentId, int $depth, array $counts): array
    {
        $nodes = [];
        foreach ($byParent[$parentId] ?? [] as $category) {
            $node = new TreeNode($category, $depth, $counts[$category->getId()] ?? 0);
            $node->children = self::level($byParent, (int) $category->getId(), $depth + 1, $counts);
            $node->total = $node->count + array_sum(array_map(static fn (TreeNode $child): int => $child->total, $node->children));
            $nodes[] = $node;
        }

        return $nodes;
    }
}
