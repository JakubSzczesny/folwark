<?php

declare(strict_types=1);

namespace App\Ksef\Xml;

final readonly class ParsedItem
{
    public function __construct(
        public int $position,
        public string $name,
        public ?string $unit,
        public ?string $quantity,
        public ?string $unitPriceNet,
        public ?string $netValue,
        public ?string $vatRate,
        public bool $beforeCorrection = false,
    ) {
    }
}
