<?php

declare(strict_types=1);

namespace App\Ksef\Dto;

use App\Ksef\SubjectType;

/**
 * Zapytanie o metadane według daty trwałego zapisu (PermanentStorage), rosnąco –
 * tak zaleca dokumentacja KSeF dla pobierania przyrostowego.
 */
final readonly class MetadataQuery
{
    public const int MAX_RANGE_DAYS = 90;
    public const int PAGE_SIZE = 250;

    public function __construct(
        public SubjectType $subjectType,
        public \DateTimeImmutable $from,
        public ?\DateTimeImmutable $to = null,
        public int $pageOffset = 0,
        public int $pageSize = self::PAGE_SIZE,
    ) {
    }

    public function withPage(int $pageOffset): self
    {
        return new self($this->subjectType, $this->from, $this->to, $pageOffset, $this->pageSize);
    }

    public function withFrom(\DateTimeImmutable $from): self
    {
        return new self($this->subjectType, $from, $this->to, 0, $this->pageSize);
    }
}
