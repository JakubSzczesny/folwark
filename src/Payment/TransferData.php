<?php

declare(strict_types=1);

namespace App\Payment;

use App\Entity\ContractorBankAccount;
use App\Entity\Invoice;
use App\Invoice\InvoiceXmlService;
use App\Settings\SettingKey;
use App\Settings\Settings;

/**
 * Dane przelewu za fakturę kosztową – do okna „Dane do przelewu” i dla dodatku BS Pay,
 * który wkleja je do formularza przelewu w bankowości internetowej.
 */
final class TransferData
{
    private const string SPLIT_PAYMENT = 'Mechanizm podzielonej płatności';

    public function __construct(
        private readonly InvoiceXmlService $xml,
        private readonly Settings $settings,
    ) {
    }

    /**
     * @return array{
     *     invoiceId: int, invoiceNumber: string, recipient: string, address: string, street: ?string, postalCode: ?string, city: ?string, nip: ?string,
     *     account: ?string, iban: ?string, accountFormatted: ?string, accountOnWhiteList: ?bool, accountSource: ?string,
     *     amount: string, vat: string, currency: string, title: string, splitPayment: bool, splitTitle: ?string,
     *     paymentDue: ?string, paid: bool, warnings: list<string>
     * }
     */
    public function forInvoice(Invoice $invoice): array
    {
        $parsed = null;
        try {
            $parsed = $this->xml->get($invoice);
        } catch (\Throwable) {
        }

        $contractor = $invoice->getContractor();
        $account = null;
        $source = null;
        foreach ($parsed->bankAccounts ?? [] as $number) {
            $account = ContractorBankAccount::normalize($number);
            $source = 'faktura';
            break;
        }

        $known = null;
        foreach ($contractor?->getBankAccounts() ?? [] as $candidate) {
            if (null !== $account && $candidate->getNumber() === $account) {
                $known = $candidate;
                break;
            }
            if (null === $account && (null === $known || true === $candidate->isOnWhiteList())) {
                $known = $candidate;
            }
        }
        if (null === $account && null !== $known) {
            $account = $known->getNumber();
            $source = 'karta kontrahenta';
        }

        $splitPayment = $this->settings->bool(SettingKey::BSPAY_SPLIT_PAYMENT) && \in_array(self::SPLIT_PAYMENT, $parsed->annotations ?? [], true);
        $nip = $contractor?->getNip() ?? $invoice->getContractorNip();
        $amount = number_format((float) $invoice->getGross(), 2, '.', '');
        $vat = number_format((float) $invoice->getVat(), 2, '.', '');

        $title = strtr($this->settings->string(SettingKey::BSPAY_TITLE) ?: 'Faktura {numer}', [
            '{numer}' => $invoice->getNumber(),
            '{data}' => $invoice->getIssueDate()->format('d.m.Y'),
            '{nip}' => (string) $nip,
        ]);

        $warnings = [];
        if (null === $account) {
            $warnings[] = 'Brak numeru rachunku – nie ma go w fakturze ani na karcie kontrahenta.';
        } elseif (false === $known?->isOnWhiteList()) {
            $warnings[] = 'Rachunek nie figuruje na białej liście VAT. Przy płatności powyżej 15 000 zł brutto grozi utrata kosztu podatkowego.';
        }
        if ('PLN' !== $invoice->getCurrency()) {
            $warnings[] = \sprintf('Faktura w walucie %s – sprawdź kwotę i rodzaj przelewu.', $invoice->getCurrency());
        }
        if ($invoice->isPaid()) {
            $warnings[] = 'Faktura jest już oznaczona jako opłacona.';
        }
        if (null === $parsed) {
            $warnings[] = 'Nie pobrano XML faktury – rachunek i podzielona płatność mogą być niepełne.';
        }

        return [
            'invoiceId' => (int) $invoice->getId(),
            'invoiceNumber' => $invoice->getNumber(),
            'recipient' => self::clean($contractor?->getName() ?? $invoice->getContractorLabel()),
            'address' => $contractor?->getAddressLine() ?? '',
            'street' => null !== $contractor?->getStreet() ? self::clean($contractor->getStreet()) : null,
            'postalCode' => $contractor?->getPostalCode(),
            'city' => null !== $contractor?->getCity() ? self::clean($contractor->getCity()) : null,
            'nip' => $nip,
            'account' => null !== $account ? self::domestic($account) : null,
            'iban' => $account,
            'accountFormatted' => null !== $account ? self::formatAccount(self::domestic($account)) : null,
            'accountOnWhiteList' => $known?->isOnWhiteList(),
            'accountSource' => $source,
            'amount' => $amount,
            'vat' => $vat,
            'currency' => $invoice->getCurrency(),
            'title' => mb_substr(self::clean($title), 0, 140),
            'splitPayment' => $splitPayment,
            'splitTitle' => $splitPayment ? \sprintf('/VAT/%s/IDC/%s/INV/%s/TXT/%s', str_replace('.', ',', $vat), (string) $nip, $invoice->getNumber(), $title) : null,
            'paymentDue' => $invoice->getPaymentDue()?->format('Y-m-d'),
            'paid' => $invoice->isPaid(),
            'warnings' => $warnings,
        ];
    }

    /**
     * Bez cudzysłowów – formularz przelewu w banku odrzuca je jako niedozwolone znaki.
     */
    public static function clean(string $text): string
    {
        return trim((string) preg_replace('/\s+/u', ' ', str_replace(['"', '„', '”', '“', '«', '»'], '', $text)));
    }

    /**
     * Polski rachunek bez prefiksu „PL” – tak wpisuje się go w przelewie krajowym.
     */
    public static function domestic(string $number): string
    {
        return 1 === preg_match('/^PL(\d{26})$/', $number, $m) ? $m[1] : $number;
    }

    public static function formatAccount(string $number): string
    {
        $prefix = '';
        if (1 === preg_match('/^[A-Z]{2}/', $number)) {
            $prefix = substr($number, 0, 2).' ';
            $number = substr($number, 2);
        }

        return 26 === \strlen($number) ? $prefix.substr($number, 0, 2).' '.implode(' ', str_split(substr($number, 2), 4)) : $prefix.$number;
    }
}
