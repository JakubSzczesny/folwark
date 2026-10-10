<?php

declare(strict_types=1);

namespace App\Ksef\Event;

use App\Entity\Invoice;
use App\Entity\KsefSync;

final class KsefSyncCompleted
{
    /**
     * @param list<Invoice> $newInvoices
     */
    public function __construct(
        public readonly KsefSync $sync,
        public readonly array $newInvoices,
    ) {
    }
}
