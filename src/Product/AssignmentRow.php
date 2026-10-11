<?php

declare(strict_types=1);

namespace App\Product;

use App\Entity\CostCenter;
use App\Entity\InvoiceItem;
use App\Entity\Product;

/**
 * Pozycja faktury na ekranie przypisywania towarów, z podpowiedzią i jej źródłem.
 */
final class AssignmentRow
{
    public const string ASSIGNED = 'assigned';
    public const string REMEMBERED = 'remembered';
    public const string PROPOSAL = 'proposal';

    public function __construct(
        public readonly InvoiceItem $item,
        public ?Product $product = null,
        public ?float $factor = null,
        public bool $skip = false,
        public ?CostCenter $costCenter = null,
        public ?string $source = null,
    ) {
    }

    public function quantityInProductUnit(): ?float
    {
        $quantity = $this->item->getQuantity();

        return null !== $this->product && null !== $this->factor && null !== $quantity ? (float) $quantity * $this->factor : null;
    }
}
