<?php

declare(strict_types=1);

namespace App\Ksef\Xml;

final readonly class ParsedParty
{
    public function __construct(
        public ?string $nip,
        public ?string $name,
        public ?string $addressLine1 = null,
        public ?string $addressLine2 = null,
        public ?string $countryCode = null,
        public ?string $email = null,
        public ?string $phone = null,
    ) {
    }
}
