<?php

declare(strict_types=1);

namespace App\Ksef\Dto;

/**
 * Metadane faktury z /invoices/query/metadata (InvoiceMetadata w API KSeF 2.0).
 */
final readonly class InvoiceMetadata
{
    public function __construct(
        public string $ksefNumber,
        public string $invoiceNumber,
        public \DateTimeImmutable $issueDate,
        public \DateTimeImmutable $acquisitionDate,
        public \DateTimeImmutable $permanentStorageDate,
        public string $sellerNip,
        public ?string $sellerName,
        public string $buyerIdentifierType,
        public ?string $buyerIdentifier,
        public ?string $buyerName,
        public string $netAmount,
        public string $vatAmount,
        public string $grossAmount,
        public string $currency,
        public string $invoiceType,
        public string $formCode,
    ) {
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function fromApi(array $data): self
    {
        $buyer = $data['buyer'] ?? [];
        $identifier = $buyer['identifier'] ?? [];

        return new self(
            ksefNumber: (string) $data['ksefNumber'],
            invoiceNumber: (string) $data['invoiceNumber'],
            issueDate: new \DateTimeImmutable((string) $data['issueDate']),
            acquisitionDate: self::dateTime((string) ($data['acquisitionDate'] ?? $data['invoicingDate'])),
            permanentStorageDate: self::dateTime((string) $data['permanentStorageDate']),
            sellerNip: (string) ($data['seller']['nip'] ?? ''),
            sellerName: isset($data['seller']['name']) ? (string) $data['seller']['name'] : null,
            buyerIdentifierType: (string) ($identifier['type'] ?? 'None'),
            buyerIdentifier: isset($identifier['value']) ? (string) $identifier['value'] : null,
            buyerName: isset($buyer['name']) ? (string) $buyer['name'] : null,
            netAmount: self::amount($data['netAmount'] ?? 0),
            vatAmount: self::amount($data['vatAmount'] ?? 0),
            grossAmount: self::amount($data['grossAmount'] ?? 0),
            currency: (string) ($data['currency'] ?? 'PLN'),
            invoiceType: (string) ($data['invoiceType'] ?? 'Vat'),
            formCode: (string) ($data['formCode']['value'] ?? 'FA'),
        );
    }

    private static function dateTime(string $value): \DateTimeImmutable
    {
        return (new \DateTimeImmutable($value))->setTimezone(new \DateTimeZone(date_default_timezone_get()));
    }

    private static function amount(mixed $value): string
    {
        return number_format((float) $value, 2, '.', '');
    }
}
