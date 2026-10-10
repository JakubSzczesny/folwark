<?php

declare(strict_types=1);

namespace App\Invoice\Event;

use App\Entity\Invoice;
use App\Ksef\Xml\ParsedInvoice;

/**
 * Pozycje faktury są już dostępne (pobrano i zapisano XML).
 */
final class InvoiceXmlLoaded
{
    public function __construct(
        public readonly Invoice $invoice,
        public readonly ParsedInvoice $parsed,
        public readonly bool $duringSync,
    ) {
    }
}
