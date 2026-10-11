<?php

declare(strict_types=1);

namespace App\Product;

use App\Entity\Product;
use App\Entity\ProductLink;
use App\Entity\ProductLinkCategory;
use App\Repository\ProductLinkCategoryRepository;
use App\Repository\ProductLinkRepository;
use App\Tree\CategoryTree;
use App\Tree\TreeNode;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Linki towaru pogrupowane w drzewie kategorii (sklepy, producent, karty charakterystyki…).
 */
final class ProductLinks
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly ProductLinkCategoryRepository $categories,
        private readonly ProductLinkRepository $links,
    ) {
    }

    /**
     * @return array{nodes: list<TreeNode<ProductLinkCategory>>, selected: ?TreeNode<ProductLinkCategory>, hidden: int}
     */
    public function tree(Product $product, ?int $selectedId = null, bool $showAll = false): array
    {
        return CategoryTree::build($this->categories->findAllOrdered(), $this->links->countByCategory($product), $selectedId, $showAll);
    }

    /**
     * Wszystkie linki towaru pogrupowane w kolejności drzewa; puste kategorie pominięte.
     *
     * @return list<array{category: ProductLinkCategory, links: list<ProductLink>}>
     */
    public function sections(Product $product): array
    {
        $byCategory = [];
        foreach ($this->links->findForProduct($product) as $link) {
            $byCategory[$link->getCategory()->getId()][] = $link;
        }

        $sections = [];
        foreach (CategoryTree::ordered($this->categories->findAllOrdered()) as $category) {
            if (isset($byCategory[$category->getId()])) {
                $sections[] = ['category' => $category, 'links' => $byCategory[$category->getId()]];
            }
        }

        return $sections;
    }

    /**
     * Kategorie do listy wyboru: ostatnio używane na górze, potem w kolejności drzewa.
     *
     * @return list<ProductLinkCategory>
     */
    public function categoryChoices(): array
    {
        $ordered = CategoryTree::ordered($this->categories->findAllOrdered());
        $recent = array_flip($this->links->recentCategoryIds());
        usort($ordered, static fn (ProductLinkCategory $a, ProductLinkCategory $b): int => ($recent[$a->getId()] ?? \PHP_INT_MAX) <=> ($recent[$b->getId()] ?? \PHP_INT_MAX));

        return $ordered;
    }

    public function addCategory(string $name, ?ProductLinkCategory $parent): ProductLinkCategory
    {
        $category = new ProductLinkCategory(trim($name), $parent, $this->categories->nextPosition($parent));
        $this->em->persist($category);
        $this->em->flush();

        return $category;
    }

    public function deleteCategory(ProductLinkCategory $category): bool
    {
        if ($this->categories->isUsed($category)) {
            return false;
        }

        $this->em->remove($category);
        $this->em->flush();

        return true;
    }

    /**
     * @param list<int> $ids
     */
    public function reorder(?ProductLinkCategory $parent, array $ids): void
    {
        CategoryTree::reorder($this->categories->findBy(['parent' => $parent]), $ids);
        $this->em->flush();
    }
}
