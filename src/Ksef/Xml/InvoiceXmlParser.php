<?php

declare(strict_types=1);

namespace App\Ksef\Xml;

use App\Ksef\KsefException;

/**
 * Odczyt faktur ustrukturyzowanych: FA(2), FA(3) (element Fa) oraz FA_RR(1) (element FakturaRR).
 * Przestrzeń nazw bierzemy z dokumentu, więc kolejne wersje schematu z tymi samymi polami też zadziałają.
 */
final class InvoiceXmlParser
{
    private const array PAYMENT_FORMS = [
        '1' => 'Gotówka',
        '2' => 'Karta',
        '3' => 'Bon',
        '4' => 'Czek',
        '5' => 'Kredyt',
        '6' => 'Przelew',
        '7' => 'Mobilna',
    ];

    /** Pola P_13_x/P_14_x i odpowiadające im stawki VAT w FA. */
    private const array VAT_FIELDS = [
        ['P_13_1', 'P_14_1', '23%'],
        ['P_13_2', 'P_14_2', '8%'],
        ['P_13_3', 'P_14_3', '5%'],
        ['P_13_4', 'P_14_4', 'ryczałt taxi'],
        ['P_13_5', 'P_14_5', 'OSS'],
        ['P_13_6_1', null, '0%'],
        ['P_13_6_2', null, '0% WDT'],
        ['P_13_6_3', null, '0% eksport'],
        ['P_13_7', null, 'zw'],
        ['P_13_8', null, 'np'],
        ['P_13_9', null, 'np (art. 100)'],
        ['P_13_10', null, 'oo'],
        ['P_13_11', null, 'marża'],
    ];

    private const array ANNOTATIONS = [
        'P_16' => 'Metoda kasowa',
        'P_17' => 'Samofakturowanie',
        'P_18' => 'Odwrotne obciążenie',
        'P_18A' => 'Mechanizm podzielonej płatności',
        'P_19' => 'Zwolnienie z VAT',
        'P_23' => 'Procedura uproszczona (transakcja trójstronna)',
    ];

    private \DOMXPath $xpath;

    public function parse(string $xml): ParsedInvoice
    {
        $document = new \DOMDocument();
        $previous = libxml_use_internal_errors(true);
        $loaded = $document->loadXML($xml, \LIBXML_NONET | \LIBXML_NOBLANKS);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        if (!$loaded || null === $document->documentElement) {
            throw new KsefException('Nieprawidłowy XML faktury.');
        }

        $this->xpath = new \DOMXPath($document);
        $this->xpath->registerNamespace('f', (string) $document->documentElement->namespaceURI);

        return $this->exists('/f:Faktura/f:FakturaRR') ? $this->parseRr() : $this->parseFa();
    }

    private function parseFa(): ParsedInvoice
    {
        $fa = '/f:Faktura/f:Fa';
        if (!$this->exists($fa)) {
            throw new KsefException('XML nie zawiera elementu Fa ani FakturaRR – nieobsługiwana struktura faktury.');
        }

        $kind = $this->text($fa.'/f:RodzajFaktury') ?? 'VAT';
        $items = [];
        foreach ($this->nodes($fa.'/f:FaWiersz') as $index => $row) {
            $before = '1' === $this->text('f:StanPrzed', $row);
            $items[] = new ParsedItem(
                position: (int) ($this->text('f:NrWierszaFa', $row) ?? $index + 1),
                name: $this->text('f:P_7', $row) ?? 'Pozycja '.($index + 1),
                unit: $this->text('f:P_8A', $row),
                quantity: self::signed($this->decimal('f:P_8B', $row), $before),
                unitPriceNet: $this->decimal('f:P_9A', $row) ?? $this->decimal('f:P_9B', $row),
                netValue: self::signed($this->decimal('f:P_11', $row) ?? $this->decimal('f:P_11A', $row), $before),
                vatRate: $this->text('f:P_12', $row),
                beforeCorrection: $before,
            );
        }

        $vatSummary = [];
        $netTotal = 0.0;
        $vatTotal = 0.0;
        foreach (self::VAT_FIELDS as [$netField, $vatField, $rate]) {
            $net = $this->decimal($fa.'/f:'.$netField);
            if (null === $net) {
                continue;
            }
            $vat = null !== $vatField ? ($this->decimal($fa.'/f:'.$vatField) ?? '0.00') : '0.00';
            $vatSummary[] = ['rate' => $rate, 'net' => $net, 'vat' => $vat];
            $netTotal += (float) $net;
            $vatTotal += (float) $vat;
        }

        $annotations = [];
        foreach (self::ANNOTATIONS as $field => $label) {
            if ('1' === $this->text($fa.'/f:Adnotacje/f:'.$field) || '1' === $this->text($fa.'/f:Adnotacje/f:Zwolnienie/f:'.$field)) {
                $annotations[] = $label;
            }
        }

        $corrected = [];
        foreach ($this->nodes($fa.'/f:DaneFaKorygowanej') as $node) {
            $corrected[] = [
                'number' => $this->text('f:NrFaKorygowanej', $node),
                'ksefNumber' => $this->text('f:NrKSeFFaKorygowanej', $node),
                'date' => $this->text('f:DataWystFaKorygowanej', $node),
            ];
        }

        $paymentForm = $this->text($fa.'/f:Platnosc/f:FormaPlatnosci');
        $paidAt = $this->text($fa.'/f:Platnosc/f:DataZaplaty');

        return new ParsedInvoice(
            formCode: 'FA',
            kindCode: $kind,
            number: $this->text($fa.'/f:P_2') ?? '',
            issueDate: $this->date($fa.'/f:P_1') ?? new \DateTimeImmutable('today'),
            saleDate: $this->date($fa.'/f:P_6') ?? $this->date($fa.'/f:OkresFa/f:P_6_Do'),
            currency: $this->text($fa.'/f:KodWaluty') ?? 'PLN',
            seller: $this->party('/f:Faktura/f:Podmiot1'),
            buyer: $this->party('/f:Faktura/f:Podmiot2'),
            net: [] === $vatSummary ? null : number_format($netTotal, 2, '.', ''),
            vat: [] === $vatSummary ? null : number_format($vatTotal, 2, '.', ''),
            gross: $this->decimal($fa.'/f:P_15'),
            items: $items,
            vatSummary: $vatSummary,
            paymentDue: $this->date($fa.'/f:Platnosc/f:TerminPlatnosci/f:Termin'),
            paymentForm: null !== $paymentForm ? (self::PAYMENT_FORMS[$paymentForm] ?? $paymentForm) : $this->text($fa.'/f:Platnosc/f:OpisPlatnosci'),
            bankAccounts: $this->bankAccounts($fa.'/f:Platnosc/f:RachunekBankowy'),
            paid: '1' === $this->text($fa.'/f:Platnosc/f:Zaplacono'),
            paidAt: null !== $paidAt ? new \DateTimeImmutable($paidAt) : null,
            correctedInvoices: $corrected,
            correctionReason: $this->text($fa.'/f:PrzyczynaKorekty'),
            annotations: $annotations,
            additionalInfo: $this->additionalInfo($fa.'/f:DodatkowyOpis'),
        );
    }

    private function parseRr(): ParsedInvoice
    {
        $rr = '/f:Faktura/f:FakturaRR';
        $items = [];
        foreach ($this->nodes($rr.'/f:FakturaRRWiersz') as $index => $row) {
            $before = '1' === $this->text('f:StanPrzed', $row);
            $items[] = new ParsedItem(
                position: (int) ($this->text('f:NrWierszaFa', $row) ?? $index + 1),
                name: $this->text('f:P_5', $row) ?? 'Pozycja '.($index + 1),
                unit: $this->text('f:P_6A', $row),
                quantity: self::signed($this->decimal('f:P_6B', $row), $before),
                unitPriceNet: $this->decimal('f:P_7', $row),
                netValue: self::signed($this->decimal('f:P_8', $row), $before),
                vatRate: $this->text('f:P_9', $row),
                beforeCorrection: $before,
            );
        }

        $net = $this->decimal($rr.'/f:P_11_1');
        $refund = $this->decimal($rr.'/f:P_11_2');
        $corrected = [];
        foreach ($this->nodes($rr.'/f:DaneFaKorygowanej') as $node) {
            $corrected[] = [
                'number' => $this->text('f:NrFaKorygowanej', $node),
                'ksefNumber' => $this->text('f:NrKSeFFaKorygowanej', $node),
                'date' => $this->text('f:DataWystFaKorygowanej', $node),
            ];
        }

        $paymentForm = $this->text($rr.'/f:Platnosc/f:FormaPlatnosci');

        return new ParsedInvoice(
            formCode: 'FA_RR',
            kindCode: $this->text($rr.'/f:RodzajFaktury') ?? 'VAT_RR',
            number: $this->text($rr.'/f:P_4C') ?? '',
            issueDate: $this->date($rr.'/f:P_4B') ?? new \DateTimeImmutable('today'),
            saleDate: $this->date($rr.'/f:P_4A'),
            currency: $this->text($rr.'/f:KodWaluty') ?? 'PLN',
            seller: $this->party('/f:Faktura/f:Podmiot1'),
            buyer: $this->party('/f:Faktura/f:Podmiot2'),
            net: $net,
            vat: $refund,
            gross: $this->decimal($rr.'/f:P_12_1'),
            items: $items,
            vatSummary: null !== $net ? [['rate' => 'zwrot RR', 'net' => $net, 'vat' => $refund ?? '0.00']] : [],
            paymentDue: null,
            paymentForm: null !== $paymentForm ? (self::PAYMENT_FORMS[$paymentForm] ?? $paymentForm) : null,
            bankAccounts: $this->bankAccounts($rr.'/f:Platnosc/f:RachunekBankowy1'),
            paid: false,
            paidAt: null,
            correctedInvoices: $corrected,
            correctionReason: $this->text($rr.'/f:PrzyczynaKorekty'),
            annotations: [],
            additionalInfo: $this->additionalInfo($rr.'/f:DodatkowyOpis'),
        );
    }

    private function party(string $path): ParsedParty
    {
        return new ParsedParty(
            nip: $this->text($path.'/f:DaneIdentyfikacyjne/f:NIP'),
            name: $this->text($path.'/f:DaneIdentyfikacyjne/f:Nazwa'),
            addressLine1: $this->text($path.'/f:Adres/f:AdresL1'),
            addressLine2: $this->text($path.'/f:Adres/f:AdresL2'),
            countryCode: $this->text($path.'/f:Adres/f:KodKraju'),
            email: $this->text($path.'/f:DaneKontaktowe/f:Email'),
            phone: $this->text($path.'/f:DaneKontaktowe/f:Telefon'),
        );
    }

    /**
     * @return list<string>
     */
    private function bankAccounts(string $path): array
    {
        $accounts = [];
        foreach ($this->nodes($path) as $node) {
            $number = $this->text('f:NrRB', $node);
            if (null !== $number) {
                $accounts[] = $number;
            }
        }

        return array_values(array_unique($accounts));
    }

    /**
     * @return list<array{key: string, value: string}>
     */
    private function additionalInfo(string $path): array
    {
        $info = [];
        foreach ($this->nodes($path) as $node) {
            $key = $this->text('f:Klucz', $node);
            $value = $this->text('f:Wartosc', $node);
            if (null !== $key && null !== $value) {
                $info[] = ['key' => $key, 'value' => $value];
            }
        }

        return $info;
    }

    private function exists(string $path): bool
    {
        return [] !== $this->nodes($path);
    }

    /**
     * @return list<\DOMElement>
     */
    private function nodes(string $path, ?\DOMNode $context = null): array
    {
        $result = $this->xpath->query($path, $context);
        if (false === $result) {
            return [];
        }

        $nodes = [];
        foreach ($result as $node) {
            if ($node instanceof \DOMElement) {
                $nodes[] = $node;
            }
        }

        return $nodes;
    }

    private function text(string $path, ?\DOMNode $context = null): ?string
    {
        $node = $this->nodes($path, $context)[0] ?? null;
        $value = null !== $node ? trim($node->textContent) : '';

        return '' === $value ? null : $value;
    }

    private function decimal(string $path, ?\DOMNode $context = null): ?string
    {
        $value = $this->text($path, $context);

        return null === $value ? null : str_replace(',', '.', $value);
    }

    private function date(string $path): ?\DateTimeImmutable
    {
        $value = $this->text($path);
        if (null === $value) {
            return null;
        }

        $date = \DateTimeImmutable::createFromFormat('!Y-m-d', substr($value, 0, 10));

        return false === $date ? null : $date;
    }

    private static function signed(?string $value, bool $negate): ?string
    {
        if (null === $value || !$negate) {
            return $value;
        }

        return str_starts_with($value, '-') ? substr($value, 1) : '-'.$value;
    }
}
