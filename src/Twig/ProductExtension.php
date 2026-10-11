<?php

declare(strict_types=1);

namespace App\Twig;

use App\Entity\OrderCycle;
use App\Entity\Product;
use App\Product\ProductPurchases;
use App\Repository\OrderCycleRepository;
use App\Repository\ProductLinkRepository;
use Twig\Attribute\AsTwigFunction;

/**
 * Skrót informacji o towarze do pokazania przy innych obiektach (np. wpis informacji o maszynie).
 */
final class ProductExtension
{
    public function __construct(
        private readonly OrderCycleRepository $cycles,
        private readonly ProductPurchases $purchases,
        private readonly ProductLinkRepository $links,
    ) {
    }

    /**
     * @return array{cycle: ?OrderCycle, last: ?array{date: \DateTimeImmutable, contractor: string, unitPrice: ?float}, links: int}
     */
    #[AsTwigFunction('product_summary')]
    public function summary(Product $product): array
    {
        $rows = $this->purchases->rows($product);

        return [
            'cycle' => $this->cycles->findOpenFor($product),
            'last' => $rows[0] ?? null,
            'links' => \count($this->links->findForProduct($product)),
        ];
    }
}
