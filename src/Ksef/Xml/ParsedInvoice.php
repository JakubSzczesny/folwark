<?php

declare(strict_types=1);

namespace App\Ksef\Xml;

final readonly class ParsedInvoice
{
    /**
     * @param list<ParsedItem>                                                 $items
     * @param list<array{rate: string, net: string, vat: string}>              $vatSummary
     * @param list<string>                                                     $bankAccounts
     * @param list<array{number: ?string, ksefNumber: ?string, date: ?string}> $correctedInvoices
     * @param list<string>                                                     $annotations
     * @param list<array{key: string, value: string}>                          $additionalInfo
     */
    public function __construct(
        public string $formCode,
        public string $kindCode,
        public string $number,
        public \DateTimeImmutable $issueDate,
        public ?\DateTimeImmutable $saleDate,
        public string $currency,
        public ParsedParty $seller,
        public ParsedParty $buyer,
        public ?string $net,
        public ?string $vat,
        public ?string $gross,
        public array $items,
        public array $vatSummary,
        public ?\DateTimeImmutable $paymentDue,
        public ?string $paymentForm,
        public array $bankAccounts,
        public bool $paid,
        public ?\DateTimeImmutable $paidAt,
        public array $correctedInvoices,
        public ?string $correctionReason,
        public array $annotations,
        public array $additionalInfo,
    ) {
    }

    public function isRr(): bool
    {
        return 'FA_RR' === $this->formCode;
    }
}
