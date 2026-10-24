<?php

declare(strict_types=1);

namespace App\Ksef\Dto;

final readonly class MetadataPage
{
    /**
     * @param list<InvoiceMetadata> $invoices
     * @param list<string>          $rejected opisy pozycji, których metadanych nie udało się odczytać (numer KSeF i powód)
     */
    public function __construct(
        public array $invoices,
        public bool $hasMore,
        public bool $isTruncated,
        public ?\DateTimeImmutable $permanentStorageHwmDate = null,
        public array $rejected = [],
    ) {
    }

    public function lastStorageDate(): ?\DateTimeImmutable
    {
        $last = $this->invoices[\count($this->invoices) - 1] ?? null;

        return $last?->permanentStorageDate;
    }
}
