<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Analytics\PriceAnalysis;
use App\Entity\Invoice;
use App\Entity\InvoiceItem;
use App\Entity\Product;
use App\Entity\Unit;
use App\Enum\InvoiceKind;
use App\Report\Reports;
use App\Tests\Factory\InvoiceFactory;
use App\Tests\Factory\UserFactory;
use Doctrine\ORM\EntityManagerInterface;
use OpenSpout\Reader\XLSX\Reader;

final class ReportAndAnalyticsTest extends FunctionalTestCase
{
    public function testDieselReportMatchesInvoiceSum(): void
    {
        $this->useFarm();
        $em = $this->service(EntityManagerInterface::class);
        $em->persist($litre = new Unit('l', 'litr'));
        $em->persist($diesel = (new Product())->setName('Olej napędowy')->setBaseUnit($litre));
        $em->flush();

        $expected = ['liters' => 0.0, 'net' => 0.0, 'gross' => 0.0];
        foreach ([['2026-02-10', 1500, '6.10'], ['2026-04-03', 2000, '5.85'], ['2026-07-31', 1200, '5.90']] as $index => [$date, $liters, $price]) {
            $net = round($liters * (float) $price, 2);
            $invoice = $this->invoice('PET/'.$index, $date, number_format($net, 2, '.', ''));
            $item = (new InvoiceItem($invoice, 1, 'Olej napędowy ON'))->setValues((string) $liters, 'l', $price, number_format($net, 2, '.', ''), '23');
            $item->assignProduct($diesel, 1.0);
            $invoice->addItem($item);
            $em->persist($item);
            $expected['liters'] += $liters;
            $expected['net'] += (float) $invoice->getNet();
            $expected['gross'] += (float) $invoice->getGross();
        }

        $mixed = $this->invoice('ORLEN/9', '2026-05-15', '1234.00');
        $fuel = (new InvoiceItem($mixed, 1, 'Olej napędowy Verva ON'))->setValues('200', 'l', '6.00', '1200.00', '23');
        $wash = (new InvoiceItem($mixed, 2, 'Myjnia'))->setValues('1', 'szt', '34.00', '34.00', '23');
        $mixed->addItem($fuel)->addItem($wash);
        $em->persist($fuel);
        $em->persist($wash);
        $expected['liters'] += 200;
        $expected['net'] += 1200.0;
        $expected['gross'] += 1476.0;

        $this->invoice('PET/old', '2026-01-31', '999.00');
        $em->flush();

        [$from, $to] = Reports::dieselPeriod(2026, 2);
        self::assertSame('2026-02-01', $from->format('Y-m-d'));
        self::assertSame('2026-07-31', $to->format('Y-m-d'));

        $report = $this->service(Reports::class)->diesel($from, $to);
        self::assertCount(4, $report->rows);
        self::assertNotNull($report->totals);
        self::assertEqualsWithDelta($expected['liters'], $report->totals[4], 0.001);
        self::assertEqualsWithDelta($expected['net'], $report->totals[5], 0.001);
        self::assertEqualsWithDelta($expected['gross'], $report->totals[7], 0.001);
    }

    public function testExportsOpenAsSpreadsheets(): void
    {
        $this->useFarm();
        $invoice = $this->invoice('FV/1/2026', '2026-09-10', '1000.00');
        $this->service(EntityManagerInterface::class)->flush();
        $this->loginAs(UserFactory::owner());

        foreach (['/zestawienia/faktury.xlsx?od=2026-09-01&do=2026-09-30&rodzaj=cost', '/zestawienia/olej-napedowy.xlsx?rok=2026&polrocze=2', '/zestawienia/zakupy.xlsx?od=2026-01-01&do=2026-12-31', '/zestawienia/terminy-maszyn.xlsx', '/zestawienia/zamowienia.xlsx?dni=90'] as $url) {
            $this->client->request('GET', $url);
            self::assertResponseIsSuccessful($url);
            $file = $this->client->getInternalResponse()->getContent();
            $path = tempnam(sys_get_temp_dir(), 'test').'.xlsx';
            file_put_contents($path, $file);
            $reader = new Reader();
            $reader->open($path);
            $rows = [];
            foreach ($reader->getSheetIterator() as $sheet) {
                foreach ($sheet->getRowIterator() as $row) {
                    $rows[] = $row->toArray();
                }
            }
            $reader->close();
            unlink($path);
            self::assertNotEmpty($rows[0] ?? [], $url.' – brak nagłówka');
            if (str_contains($url, 'faktury')) {
                self::assertSame($invoice->getNumber(), $rows[1][1]);
                self::assertEqualsWithDelta(1000.0, $rows[1][5], 0.001);
            }
        }

        foreach (['/zestawienia/faktury.pdf?od=2026-09-01&do=2026-09-30', '/zestawienia/olej-napedowy.pdf?rok=2026&polrocze=2'] as $url) {
            $this->client->request('GET', $url);
            self::assertResponseIsSuccessful($url);
            self::assertResponseHeaderSame('content-type', 'application/pdf');
        }

        $this->client->request('GET', '/zestawienia');
        self::assertSelectorTextContains('main', 'Olej napędowy – zwrot akcyzy');
    }

    public function testAnalyticsPagesAndPriceWarning(): void
    {
        $this->useFarm();
        $em = $this->service(EntityManagerInterface::class);
        $em->persist($kg = new Unit('kg', 'kilogram'));
        $em->persist($feed = (new Product())->setName('Mieszanka paszowa')->setBaseUnit($kg));
        $em->flush();

        $today = new \DateTimeImmutable('today');
        $last = null;
        foreach ([[120, '2.00'], [90, '2.10'], [60, '1.90'], [5, '2.40']] as $index => [$daysAgo, $price]) {
            $net = number_format(1000 * (float) $price, 2, '.', '');
            $last = $this->invoice('PASZ/'.$index, $today->modify("-{$daysAgo} days")->format('Y-m-d'), $net);
            $item = (new InvoiceItem($last, 1, 'Mieszanka MK-18'))->setValues('1000', 'kg', $price, $net, '8');
            $item->assignProduct($feed, 1.0);
            $last->addItem($item);
            $em->persist($item);
        }
        $em->flush();
        self::assertInstanceOf(Invoice::class, $last);

        $alerts = $this->service(PriceAnalysis::class)->alertsForInvoice($last);
        self::assertCount(1, $alerts);
        self::assertEqualsWithDelta(0.2, array_values($alerts)[0]['percent'], 0.0001, '2,40 zł wobec mediany 2,00 zł');

        $this->loginAs(UserFactory::owner());
        foreach (['/analizy', '/analizy/ceny', '/analizy/osrodki', '/analizy/zuzycie', '/analizy/przychody'] as $url) {
            $this->client->request('GET', $url);
            self::assertResponseIsSuccessful($url);
        }
        $this->client->request('GET', '/analizy/ceny');
        self::assertSelectorTextContains('main', 'Mieszanka paszowa');
        self::assertSelectorTextContains('main', 'PASZ/3');

        $this->client->request('GET', '/faktury/'.$last->getId());
        self::assertSelectorTextContains('main', 'powyżej mediany');
    }

    private function invoice(string $number, string $date, string $net): Invoice
    {
        $vat = number_format(round((float) $net * 0.23, 2), 2, '.', '');
        $invoice = InvoiceFactory::createOne(['number' => $number, 'kind' => InvoiceKind::Cost, 'issueDate' => new \DateTimeImmutable($date)]);
        $invoice->setAmounts($net, $vat, number_format((float) $net + (float) $vat, 2, '.', ''));

        return $invoice;
    }
}
