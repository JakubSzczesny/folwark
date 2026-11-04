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
     * @param list<array{reason: string, amount: string}>                      $charges        Rozliczenie/Obciazenia – kwoty doliczane do P_15
     * @param list<array{reason: string, amount: string}>                      $deductions     Rozliczenie/Odliczenia – kwoty odliczane od P_15
     * @param ?string                                                          $amountDue      kwota do zapłaty po rozliczeniu (ujemna = nadpłata do zwrotu), null gdy faktura nie ma sekcji Rozliczenie
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
        public array $charges = [],
        public array $deductions = [],
        public ?string $amountDue = null,
    ) {
    }

    public function hasSettlement(): bool
    {
        return [] !== $this->charges || [] !== $this->deductions || null !== $this->amountDue;
    }

    public function isRr(): bool
    {
        return 'FA_RR' === $this->formCode;
    }
}
