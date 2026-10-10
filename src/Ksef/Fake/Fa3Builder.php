<?php

declare(strict_types=1);

namespace App\Ksef\Fake;

/**
 * Buduje XML faktury w strukturze FA(3) – na potrzeby symulatora KSeF, testów i danych demonstracyjnych.
 */
final class Fa3Builder
{
    public const string NAMESPACE = 'http://crd.gov.pl/wzor/2025/06/25/13775/';

    /** Stawka => [pole netto, pole VAT]. */
    private const array RATE_FIELDS = [
        '23' => ['P_13_1', 'P_14_1'],
        '8' => ['P_13_2', 'P_14_2'],
        '5' => ['P_13_3', 'P_14_3'],
        '0' => ['P_13_6_1', null],
        'zw' => ['P_13_7', null],
    ];

    /** @var list<array{name: string, unit: ?string, quantity: ?float, price: ?float, rate: string, before: bool}> */
    private array $items = [];
    private string $kind = 'VAT';
    private string $currency = 'PLN';
    private ?string $sellerAddress1 = null;
    private ?string $sellerAddress2 = null;
    private ?string $buyerAddress1 = null;
    private ?string $buyerAddress2 = null;
    private ?\DateTimeImmutable $paymentDue = null;
    private ?string $paymentForm = '6';
    private ?string $bankAccount = null;
    private ?\DateTimeImmutable $paidAt = null;
    /** @var array{number: string, ksefNumber: ?string, date: \DateTimeImmutable}|null */
    private ?array $corrected = null;

    private function __construct(
        private readonly string $sellerNip,
        private readonly string $sellerName,
        private readonly ?string $buyerNip,
        private readonly string $buyerName,
        private readonly string $number,
        private readonly \DateTimeImmutable $issueDate,
    ) {
    }

    public static function invoice(string $sellerNip, string $sellerName, ?string $buyerNip, string $buyerName, string $number, \DateTimeImmutable $issueDate): self
    {
        return new self($sellerNip, $sellerName, $buyerNip, $buyerName, $number, $issueDate);
    }

    public function sellerAddress(string $line1, ?string $line2 = null): self
    {
        $this->sellerAddress1 = $line1;
        $this->sellerAddress2 = $line2;

        return $this;
    }

    public function buyerAddress(string $line1, ?string $line2 = null): self
    {
        $this->buyerAddress1 = $line1;
        $this->buyerAddress2 = $line2;

        return $this;
    }

    public function item(string $name, ?string $unit, ?float $quantity, ?float $netPrice, string $rate = '23', bool $beforeCorrection = false): self
    {
        $this->items[] = ['name' => $name, 'unit' => $unit, 'quantity' => $quantity, 'price' => $netPrice, 'rate' => $rate, 'before' => $beforeCorrection];

        return $this;
    }

    public function paymentDue(?\DateTimeImmutable $due, ?string $form = '6'): self
    {
        $this->paymentDue = $due;
        $this->paymentForm = $form;

        return $this;
    }

    public function bankAccount(string $number): self
    {
        $this->bankAccount = $number;

        return $this;
    }

    public function paid(\DateTimeImmutable $paidAt): self
    {
        $this->paidAt = $paidAt;

        return $this;
    }

    public function currency(string $currency): self
    {
        $this->currency = $currency;

        return $this;
    }

    public function correctionOf(string $number, ?string $ksefNumber, \DateTimeImmutable $date): self
    {
        $this->kind = 'KOR';
        $this->corrected = ['number' => $number, 'ksefNumber' => $ksefNumber, 'date' => $date];

        return $this;
    }

    public function advance(): self
    {
        $this->kind = 'ZAL';

        return $this;
    }

    public function toXml(): string
    {
        $doc = new \DOMDocument('1.0', 'UTF-8');
        $doc->formatOutput = true;
        $root = $doc->createElementNS(self::NAMESPACE, 'Faktura');
        $doc->appendChild($root);

        $header = $this->append($root, 'Naglowek');
        $code = $this->append($header, 'KodFormularza', 'FA');
        $code->setAttribute('kodSystemowy', 'FA (3)');
        $code->setAttribute('wersjaSchemy', '1-0E');
        $this->append($header, 'WariantFormularza', '3');
        $this->append($header, 'DataWytworzeniaFa', $this->issueDate->format('Y-m-d').'T10:00:00Z');
        $this->append($header, 'SystemInfo', 'Folwark – symulator KSeF');

        $seller = $this->append($root, 'Podmiot1');
        $sellerId = $this->append($seller, 'DaneIdentyfikacyjne');
        $this->append($sellerId, 'NIP', $this->sellerNip);
        $this->append($sellerId, 'Nazwa', $this->sellerName);
        $this->address($seller, $this->sellerAddress1, $this->sellerAddress2);

        $buyer = $this->append($root, 'Podmiot2');
        $buyerId = $this->append($buyer, 'DaneIdentyfikacyjne');
        if (null !== $this->buyerNip) {
            $this->append($buyerId, 'NIP', $this->buyerNip);
        } else {
            $this->append($buyerId, 'BrakID', '1');
        }
        $this->append($buyerId, 'Nazwa', $this->buyerName);
        $this->address($buyer, $this->buyerAddress1, $this->buyerAddress2);
        $this->append($buyer, 'JST', '2');
        $this->append($buyer, 'GV', '2');

        $fa = $this->append($root, 'Fa');
        $this->append($fa, 'KodWaluty', $this->currency);
        $this->append($fa, 'P_1', $this->issueDate->format('Y-m-d'));
        $this->append($fa, 'P_2', $this->number);
        $this->append($fa, 'P_6', $this->issueDate->format('Y-m-d'));

        $totals = $this->totals();
        $gross = 0.0;
        foreach (self::RATE_FIELDS as $rate => [$netField, $vatField]) {
            if (!isset($totals[$rate])) {
                continue;
            }
            [$net, $vat] = $totals[$rate];
            $this->append($fa, $netField, self::amount($net));
            if (null !== $vatField) {
                $this->append($fa, $vatField, self::amount($vat));
            }
            $gross += $net + $vat;
        }
        $this->append($fa, 'P_15', self::amount($gross));

        $annotations = $this->append($fa, 'Adnotacje');
        foreach (['P_16' => '2', 'P_17' => '2', 'P_18' => '2', 'P_18A' => '2'] as $field => $value) {
            $this->append($annotations, $field, $value);
        }
        $exemption = $this->append($annotations, 'Zwolnienie');
        $this->append($exemption, 'P_19N', '1');
        $transport = $this->append($annotations, 'NoweSrodkiTransportu');
        $this->append($transport, 'P_22N', '1');
        $this->append($annotations, 'P_23', '2');
        $margin = $this->append($annotations, 'PMarzy');
        $this->append($margin, 'P_PMarzyN', '1');

        $this->append($fa, 'RodzajFaktury', $this->kind);
        if (null !== $this->corrected) {
            $this->append($fa, 'PrzyczynaKorekty', 'Korekta ilości');
            $this->append($fa, 'TypKorekty', '2');
            $data = $this->append($fa, 'DaneFaKorygowanej');
            $this->append($data, 'DataWystFaKorygowanej', $this->corrected['date']->format('Y-m-d'));
            $this->append($data, 'NrFaKorygowanej', $this->corrected['number']);
            if (null !== $this->corrected['ksefNumber']) {
                $this->append($data, 'NrKSeF', '1');
                $this->append($data, 'NrKSeFFaKorygowanej', $this->corrected['ksefNumber']);
            } else {
                $this->append($data, 'NrKSeFN', '1');
            }
        }

        foreach ($this->items as $index => $item) {
            $row = $this->append($fa, 'FaWiersz');
            $this->append($row, 'NrWierszaFa', (string) ($index + 1));
            $this->append($row, 'P_7', $item['name']);
            if (null !== $item['unit']) {
                $this->append($row, 'P_8A', $item['unit']);
            }
            if (null !== $item['quantity']) {
                $this->append($row, 'P_8B', self::quantity($item['quantity']));
            }
            if (null !== $item['price']) {
                $this->append($row, 'P_9A', self::quantity($item['price']));
            }
            $this->append($row, 'P_11', self::amount(self::lineNet($item)));
            $this->append($row, 'P_12', $item['rate']);
            if ($item['before']) {
                $this->append($row, 'StanPrzed', '1');
            }
        }

        if (null !== $this->paymentDue || null !== $this->paidAt || null !== $this->bankAccount) {
            $payment = $this->append($fa, 'Platnosc');
            if (null !== $this->paidAt) {
                $this->append($payment, 'Zaplacono', '1');
                $this->append($payment, 'DataZaplaty', $this->paidAt->format('Y-m-d'));
            }
            if (null !== $this->paymentDue) {
                $term = $this->append($payment, 'TerminPlatnosci');
                $this->append($term, 'Termin', $this->paymentDue->format('Y-m-d'));
            }
            if (null !== $this->paymentForm) {
                $this->append($payment, 'FormaPlatnosci', $this->paymentForm);
            }
            if (null !== $this->bankAccount) {
                $account = $this->append($payment, 'RachunekBankowy');
                $this->append($account, 'NrRB', $this->bankAccount);
            }
        }

        return (string) $doc->saveXML();
    }

    /**
     * @return array<string, array{float, float}>
     */
    private function totals(): array
    {
        $totals = [];
        foreach ($this->items as $item) {
            $net = self::lineNet($item);
            $rate = $item['rate'];
            $vat = is_numeric($rate) ? round($net * (float) $rate / 100, 2) : 0.0;
            $totals[$rate] ??= [0.0, 0.0];
            $totals[$rate][0] += $net;
            $totals[$rate][1] += $vat;
        }

        return $totals;
    }

    /**
     * @param array{name: string, unit: ?string, quantity: ?float, price: ?float, rate: string, before: bool} $item
     */
    private static function lineNet(array $item): float
    {
        $net = round(($item['quantity'] ?? 1.0) * ($item['price'] ?? 0.0), 2);

        return $item['before'] ? -$net : $net;
    }

    private function address(\DOMElement $parent, ?string $line1, ?string $line2): void
    {
        if (null === $line1) {
            return;
        }

        $address = $this->append($parent, 'Adres');
        $this->append($address, 'KodKraju', 'PL');
        $this->append($address, 'AdresL1', $line1);
        if (null !== $line2) {
            $this->append($address, 'AdresL2', $line2);
        }
    }

    private function append(\DOMElement|\DOMDocument $parent, string $name, ?string $value = null): \DOMElement
    {
        $document = $parent instanceof \DOMDocument ? $parent : $parent->ownerDocument;
        \assert($document instanceof \DOMDocument);
        $element = $document->createElementNS(self::NAMESPACE, $name);
        if (null !== $value) {
            $element->appendChild($document->createTextNode($value));
        }
        $parent->appendChild($element);

        return $element;
    }

    private static function amount(float $value): string
    {
        return number_format($value, 2, '.', '');
    }

    private static function quantity(float $value): string
    {
        return rtrim(rtrim(number_format($value, 4, '.', ''), '0'), '.');
    }
}
