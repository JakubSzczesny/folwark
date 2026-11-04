<?php

declare(strict_types=1);

namespace App\Search;

final readonly class SearchResult
{
    public function __construct(
        public string $title,
        public string $url,
        public ?string $subtitle = null,
        /** Adres poza Folwarkiem – otwiera się w nowej karcie. */
        public bool $external = false,
    ) {
    }
}
