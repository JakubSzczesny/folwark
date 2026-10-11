<?php

declare(strict_types=1);

namespace App\Search;

use App\Entity\Product;
use App\Repository\ProductRepository;
use App\Security\Permission;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

final class ProductSearchProvider implements SearchProvider
{
    public function __construct(
        private readonly ProductRepository $products,
        private readonly UrlGeneratorInterface $urls,
    ) {
    }

    public function group(): string
    {
        return 'Towary';
    }

    public function permission(): string
    {
        return Permission::PRODUCT_VIEW;
    }

    public function search(string $query, int $limit): array
    {
        return array_map(
            fn (Product $product): SearchResult => new SearchResult(
                $product->getName(),
                $this->urls->generate('app_product_show', ['id' => $product->getId()]),
                implode(' · ', array_filter([$product->getCategory()?->getName(), $product->getCatalogNumber(), $product->isActive() ? null : 'nieaktywny'])),
            ),
            $this->products->search($query, $limit),
        );
    }
}
