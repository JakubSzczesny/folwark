<?php

declare(strict_types=1);

namespace App\DataFixtures;

use App\Ksef\Fake\Fa3Builder;
use App\Ksef\Fake\FakeKsefClient;

/**
 * Generuje historię faktur gospodarstwa do symulatora KSeF (pliki XML + index.json).
 * Dane są fikcyjne, ale powtarzalne (stałe ziarno losowania).
 */
final class DemoInvoiceGenerator
{
    public const string FARM_NIP = '5841000001';
    public const string FARM_NAME = 'Gospodarstwo Rolne Kowalscy';

    /** @var array<string, array{nip: string, name: string, street: string, city: string, account: string}> */
    public const array PARTNERS = [
        'petromex' => ['nip' => '7311000000', 'name' => 'PETROMEX Sp. z o.o.', 'street' => 'ul. Paliwowa 12', 'city' => '87-100 Toruń', 'account' => 'PL61109010140000071219812874'],
        'pasze' => ['nip' => '5261000007', 'name' => 'Agro-Pasze S.A.', 'street' => 'ul. Młyńska 3', 'city' => '86-300 Grudziądz', 'account' => 'PL27114020040000300201355387'],
        'nawozy' => ['nip' => '7121000008', 'name' => 'Centrala Nawozowa ROL-CHEM Sp. z o.o.', 'street' => 'ul. Fabryczna 8', 'city' => '88-100 Inowrocław', 'account' => 'PL83101010230000261395100000'],
        'nasiona' => ['nip' => '7741000008', 'name' => 'Hodowla Roślin Zbożowych Sp. z o.o.', 'street' => 'Strzelce 1', 'city' => '99-307 Strzelce', 'account' => 'PL50124050011111000049123456'],
        'serwis' => ['nip' => '8511000005', 'name' => 'AGRO-SERWIS Mariusz Wiśniewski', 'street' => 'ul. Kombajnowa 4', 'city' => '87-200 Wąbrzeźno', 'account' => 'PL19105000861000002201234567'],
        'energia' => ['nip' => '6791000004', 'name' => 'Energa Obrót S.A.', 'street' => 'al. Grunwaldzka 472', 'city' => '80-309 Gdańsk', 'account' => 'PL12116022020000000123456789'],
        'ubezpieczenia' => ['nip' => '9441000005', 'name' => 'TUW Rolnik Ubezpieczenia', 'street' => 'ul. Polna 50', 'city' => '00-635 Warszawa', 'account' => 'PL90102010260000150201234567'],
        'weterynarz' => ['nip' => '6251000006', 'name' => 'Gabinet Weterynaryjny VET-MED', 'street' => 'ul. Lipowa 7', 'city' => '87-140 Chełmża', 'account' => 'PL71249010440000420012345678'],
        'mleczarnia' => ['nip' => '1131000001', 'name' => 'Spółdzielnia Mleczarska Dolina Wisły', 'street' => 'ul. Mleczna 1', 'city' => '86-200 Chełmno', 'account' => 'PL10105000997603123456789123'],
        'elewator' => ['nip' => '8961000005', 'name' => 'Elewator Zbożowy Kujawy Sp. z o.o.', 'street' => 'ul. Kolejowa 22', 'city' => '88-300 Mogilno', 'account' => 'PL44116022020000000987654321'],
    ];

    /** @var list<array{xml: string, storedAt: \DateTimeImmutable, partner: string}> */
    private array $invoices = [];

    /** @var array<string, int> */
    private array $numbers = [];

    public function generate(string $directory, \DateTimeImmutable $from, \DateTimeImmutable $to): int
    {
        mt_srand(2026);
        $this->invoices = [];

        $this->fuel($from, $to);
        $this->feed($from, $to);
        $this->minerals($from, $to);
        $this->fertilizers($from, $to);
        $this->seeds($from, $to);
        $this->service($from, $to);
        $this->energy($from, $to);
        $this->insurance($from, $to);
        $this->vet($from, $to);
        $this->milk($from, $to);
        $this->grain($from, $to);

        if (!is_dir($directory) && !mkdir($directory, 0o775, true) && !is_dir($directory)) {
            throw new \RuntimeException('Nie można utworzyć katalogu '.$directory);
        }
        foreach (glob($directory.'/*.xml') ?: [] as $old) {
            unlink($old);
        }

        $index = [];
        foreach ($this->invoices as $i => $invoice) {
            $file = \sprintf('%04d.xml', $i + 1);
            file_put_contents($directory.'/'.$file, $invoice['xml']);
            $parsed = simplexml_load_string($invoice['xml']);
            \assert(false !== $parsed);
            $parsed->registerXPathNamespace('f', Fa3Builder::NAMESPACE);
            $number = (string) ($parsed->xpath('//f:Fa/f:P_2')[0] ?? '');
            $sellerNip = (string) ($parsed->xpath('//f:Podmiot1/f:DaneIdentyfikacyjne/f:NIP')[0] ?? '');
            $issueDate = new \DateTimeImmutable((string) ($parsed->xpath('//f:Fa/f:P_1')[0] ?? 'today'));
            $index[] = [
                'file' => $file,
                'ksefNumber' => FakeKsefClient::ksefNumber($sellerNip, $issueDate, $number),
                'permanentStorageDate' => $invoice['storedAt']->format(\DATE_ATOM),
            ];
        }
        file_put_contents($directory.'/index.json', json_encode($index, \JSON_PRETTY_PRINT));

        return \count($this->invoices);
    }

    private function fuel(\DateTimeImmutable $from, \DateTimeImmutable $to): void
    {
        for ($date = $from->modify('+3 days'); $date <= $to; $date = $date->modify(\sprintf('+%d days', 19 + mt_rand(0, 4)))) {
            $litres = 50 * mt_rand(60, 80);
            $price = 5.62 + mt_rand(-25, 30) / 100;
            $this->purchase('petromex', $date, 14, [['Olej napędowy ON', 'l', $litres, $price, '23']]);
        }
    }

    private function feed(\DateTimeImmutable $from, \DateTimeImmutable $to): void
    {
        for ($date = $from->modify('+8 days'); $date <= $to; $date = $date->modify(\sprintf('+%d days', 28 + mt_rand(0, 5)))) {
            $tons = mt_rand(110, 130) / 10;
            $this->purchase('pasze', $date, 30, [
                ['Mieszanka paszowa MLEKO 18% luzem', 't', $tons, 1640 + mt_rand(-40, 60), '8'],
                ['Transport', 'kurs', 1, 180, '23'],
            ]);
        }
    }

    private function minerals(\DateTimeImmutable $from, \DateTimeImmutable $to): void
    {
        for ($date = $from->modify('+20 days'); $date <= $to; $date = $date->modify(\sprintf('+%d days', 55 + mt_rand(0, 15)))) {
            $this->purchase('pasze', $date, 30, [['Dodatek mineralny Bovi-Min worek 25kg', 'szt', 20 * mt_rand(2, 4), 96.5, '8']]);
        }
    }

    private function fertilizers(\DateTimeImmutable $from, \DateTimeImmutable $to): void
    {
        for ($year = (int) $from->format('Y'); $year <= (int) $to->format('Y'); ++$year) {
            foreach ([[3, 4, 24.0], [3, 19, 18.0]] as [$month, $day, $tons]) {
                $date = new \DateTimeImmutable(\sprintf('%d-%02d-%02d', $year, $month, $day + mt_rand(-2, 2)));
                if ($date >= $from && $date <= $to) {
                    $this->purchase('nawozy', $date, 21, [['Saletra amonowa 34% big bag 500kg', 't', $tons, 1890 + mt_rand(-50, 80), '8']]);
                }
            }
            $date = new \DateTimeImmutable(\sprintf('%d-08-%02d', $year, 20 + mt_rand(0, 6)));
            if ($date >= $from && $date <= $to) {
                $this->purchase('nawozy', $date, 21, [['Polifoska 6 big bag 500kg', 't', 15.0, 2350 + mt_rand(-40, 40), '8']]);
            }
        }
    }

    private function seeds(\DateTimeImmutable $from, \DateTimeImmutable $to): void
    {
        for ($year = (int) $from->format('Y'); $year <= (int) $to->format('Y'); ++$year) {
            $date = new \DateTimeImmutable(\sprintf('%d-09-%02d', $year, 8 + mt_rand(0, 6)));
            if ($date >= $from && $date <= $to) {
                $this->purchase('nasiona', $date, 30, [['Pszenica ozima KWS Donovan C1 worek 500kg', 't', 9.5, 2950, '8']]);
            }
        }
    }

    private function service(\DateTimeImmutable $from, \DateTimeImmutable $to): void
    {
        for ($date = $from->modify('+15 days'); $date <= $to; $date = $date->modify(\sprintf('+%d days', 40 + mt_rand(0, 50)))) {
            $items = [
                ['Filtr oleju silnika JD RE504836', 'szt', 2, 89.0, '23'],
                ['Olej silnikowy 15W40 5L', 'szt', mt_rand(2, 4), 139.0, '23'],
            ];
            if (mt_rand(0, 1)) {
                $items[] = ['Filtr paliwa RE522878', 'szt', 1, 154.0, '23'];
            }
            $items[] = ['Robocizna serwisowa', 'h', mt_rand(2, 5), 150.0, '23'];
            $this->purchase('serwis', $date, 14, $items);
        }
    }

    private function energy(\DateTimeImmutable $from, \DateTimeImmutable $to): void
    {
        for ($date = $from->modify('first day of next month')->modify('+4 days'); $date <= $to; $date = $date->modify('first day of next month')->modify('+4 days')) {
            $this->purchase('energia', $date, 14, [['Energia elektryczna – rozliczenie za '.$date->modify('-1 month')->format('m/Y'), 'kWh', mt_rand(5200, 7400), 0.71, '23']]);
        }
    }

    private function insurance(\DateTimeImmutable $from, \DateTimeImmutable $to): void
    {
        for ($year = (int) $from->format('Y'); $year <= (int) $to->format('Y'); ++$year) {
            foreach (['01-15' => 'Ubezpieczenie OC ciągników i przyczep – rata', '07-15' => 'Ubezpieczenie budynków gospodarskich – rata'] as $day => $name) {
                $date = new \DateTimeImmutable($year.'-'.$day);
                if ($date >= $from && $date <= $to) {
                    $this->purchase('ubezpieczenia', $date, 21, [[$name, 'szt', 1, 4200, 'zw']]);
                }
            }
        }
    }

    private function vet(\DateTimeImmutable $from, \DateTimeImmutable $to): void
    {
        for ($date = $from->modify('+11 days'); $date <= $to; $date = $date->modify(\sprintf('+%d days', 25 + mt_rand(0, 60)))) {
            $this->purchase('weterynarz', $date, 7, [
                ['Wizyta lekarsko-weterynaryjna', 'usł', 1, 180, '8'],
                ['Lek Synulox LC tubostrzykawka', 'szt', mt_rand(4, 12), 18.5, '8'],
            ]);
        }
    }

    private function milk(\DateTimeImmutable $from, \DateTimeImmutable $to): void
    {
        for ($date = $from->modify('first day of next month')->modify('+9 days'); $date <= $to; $date = $date->modify('first day of next month')->modify('+9 days')) {
            $litres = mt_rand(52000, 61000);
            $this->sale('mleczarnia', $date, 14, [['Mleko surowe klasy ekstra – dostawy '.$date->modify('-1 month')->format('m/Y'), 'l', $litres, 2.18 + mt_rand(-8, 10) / 100, '5']]);
        }
    }

    private function grain(\DateTimeImmutable $from, \DateTimeImmutable $to): void
    {
        foreach (['2025-08-06' => 61.4, '2025-08-21' => 58.9, '2026-08-04' => 48.3, '2026-08-18' => 52.7, '2026-09-09' => 41.3] as $day => $tons) {
            $date = new \DateTimeImmutable($day);
            if ($date >= $from && $date <= $to) {
                $this->sale('elewator', $date, 30, [['Pszenica konsumpcyjna kl. I', 't', $tons, 865, '5']]);
            }
        }
    }

    /**
     * @param list<array{string, string, float|int, float|int, string}> $items
     */
    private function purchase(string $partner, \DateTimeImmutable $date, int $paymentDays, array $items): void
    {
        $p = self::PARTNERS[$partner];
        $builder = Fa3Builder::invoice($p['nip'], $p['name'], self::FARM_NIP, self::FARM_NAME, $this->number($partner, $date), $date)
            ->sellerAddress($p['street'], $p['city'])
            ->buyerAddress('Zielona Wieś 15', '87-140 Chełmża')
            ->paymentDue($date->modify(\sprintf('+%d days', $paymentDays)))
            ->bankAccount($p['account']);

        foreach ($items as [$name, $unit, $quantity, $price, $rate]) {
            $builder->item($name, $unit, (float) $quantity, round((float) $price, 2), $rate);
        }

        if ($date->modify(\sprintf('+%d days', $paymentDays + 5)) < new \DateTimeImmutable('today') && mt_rand(0, 9) > 0) {
            $builder->paid($date->modify(\sprintf('+%d days', mt_rand(1, $paymentDays))));
        }

        $this->add($builder->toXml(), $date, $partner);
    }

    /**
     * @param list<array{string, string, float|int, float|int, string}> $items
     */
    private function sale(string $partner, \DateTimeImmutable $date, int $paymentDays, array $items): void
    {
        $p = self::PARTNERS[$partner];
        $builder = Fa3Builder::invoice(self::FARM_NIP, self::FARM_NAME, $p['nip'], $p['name'], $this->number('farm', $date), $date)
            ->sellerAddress('Zielona Wieś 15', '87-140 Chełmża')
            ->buyerAddress($p['street'], $p['city'])
            ->paymentDue($date->modify(\sprintf('+%d days', $paymentDays)))
            ->bankAccount('PL55102010260000110201234567');

        foreach ($items as [$name, $unit, $quantity, $price, $rate]) {
            $builder->item($name, $unit, (float) $quantity, round((float) $price, 4), $rate);
        }

        $this->add($builder->toXml(), $date, $partner);
    }

    private function add(string $xml, \DateTimeImmutable $date, string $partner): void
    {
        $this->invoices[] = ['xml' => $xml, 'storedAt' => $date->setTime(mt_rand(8, 17), mt_rand(0, 59)), 'partner' => $partner];
    }

    private function number(string $partner, \DateTimeImmutable $date): string
    {
        $key = $partner.$date->format('Y');
        $this->numbers[$key] = ($this->numbers[$key] ?? 0) + 1;
        $prefix = 'farm' === $partner ? 'FV' : strtoupper(substr($partner, 0, 3));

        return \sprintf('%s/%d/%s', $prefix, $this->numbers[$key], $date->format('m/Y'));
    }
}
