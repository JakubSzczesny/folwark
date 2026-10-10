<?php

declare(strict_types=1);

namespace App\Ksef\Dto;

final readonly class MetadataPage
{
    /**
     * @param list<InvoiceMetadata> $invoices
     */
    public function __construct(
        public array $invoices,
        public bool $hasMore,
        public bool $isTruncated,
        public ?\DateTimeImmutable $permanentStorageHwmDate = null,
    ) {
    }

    public function lastStorageDate(): ?\DateTimeImmutable
    {
        $last = $this->invoices[\count($this->invoices) - 1] ?? null;

        return $last?->permanentStorageDate;
    }
}
