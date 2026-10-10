<?php

declare(strict_types=1);

namespace App\WhiteList;

/**
 * Podmiot z Wykazu podatników VAT (pole result.subject w API wl-api.mf.gov.pl).
 */
final readonly class WhiteListSubject
{
    /**
     * @param list<string> $accountNumbers
     */
    public function __construct(
        public string $nip,
        public string $name,
        public ?string $statusVat,
        public ?string $address,
        public array $accountNumbers,
    ) {
    }

    /**
     * @param array<string, mixed> $subject
     */
    public static function fromApi(array $subject): self
    {
        return new self(
            nip: (string) ($subject['nip'] ?? ''),
            name: (string) ($subject['name'] ?? ''),
            statusVat: isset($subject['statusVat']) ? (string) $subject['statusVat'] : null,
            address: isset($subject['workingAddress']) ? (string) $subject['workingAddress'] : (isset($subject['residenceAddress']) ? (string) $subject['residenceAddress'] : null),
            accountNumbers: array_values(array_map('strval', (array) ($subject['accountNumbers'] ?? []))),
        );
    }
}
