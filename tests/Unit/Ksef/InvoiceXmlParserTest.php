<?php

declare(strict_types=1);

namespace App\Tests\Unit\Ksef;

use App\Ksef\Fake\Fa3Builder;
use App\Ksef\KsefException;
use App\Ksef\Xml\InvoiceXmlParser;
use PHPUnit\Framework\TestCase;

final class InvoiceXmlParserTest extends TestCase
{
    public function testParsesFa3Invoice(): void
    {
        $xml = Fa3Builder::invoice('7311000000', 'PETROMEX Sp. z o.o.', '5841000001', 'Gospodarstwo', 'PET/7/2026', new \DateTimeImmutable('2026-05-04'))
            ->sellerAddress('ul. Paliwowa 12', '87-100 Toruń')
            ->item('Olej napędowy ON', 'l', 3500, 5.4, '23')
            ->item('Transport', 'kurs', 1, 100, '8')
            ->paymentDue(new \DateTimeImmutable('2026-05-18'))
            ->bankAccount('PL61109010140000071219812874')
            ->toXml();

        $parsed = (new InvoiceXmlParser())->parse($xml);

        self::assertSame('FA', $parsed->formCode);
        self::assertSame('VAT', $parsed->kindCode);
        self::assertSame('PET/7/2026', $parsed->number);
        self::assertSame('2026-05-04', $parsed->issueDate->format('Y-m-d'));
        self::assertSame('7311000000', $parsed->seller->nip);
        self::assertSame('87-100 Toruń', $parsed->seller->addressLine2);
        self::assertSame('5841000001', $parsed->buyer->nip);
        self::assertCount(2, $parsed->items);
        self::assertSame('Olej napędowy ON', $parsed->items[0]->name);
        self::assertSame('3500', $parsed->items[0]->quantity);
        self::assertSame('l', $parsed->items[0]->unit);
        self::assertSame('18900.00', $parsed->items[0]->netValue);
        self::assertSame('19000.00', $parsed->net);
        self::assertSame('4355.00', $parsed->vat);
        self::assertSame('23355.00', $parsed->gross);
        self::assertSame('2026-05-18', $parsed->paymentDue?->format('Y-m-d'));
        self::assertSame('Przelew', $parsed->paymentForm);
        self::assertSame(['PL61109010140000071219812874'], $parsed->bankAccounts);
        self::assertFalse($parsed->paid);
    }

    public function testCorrectionRowsBeforeAreNegative(): void
    {
        $xml = Fa3Builder::invoice('7311000000', 'PETROMEX', '5841000001', 'Gospodarstwo', 'KOR/1/2026', new \DateTimeImmutable('2026-05-20'))
            ->correctionOf('PET/7/2026', '7311000000-20260504-AAAAAA-BBBBBB-CC', new \DateTimeImmutable('2026-05-04'))
            ->item('Olej napędowy ON', 'l', 3500, 5.4, '23', true)
            ->item('Olej napędowy ON', 'l', 3000, 5.4, '23')
            ->toXml();

        $parsed = (new InvoiceXmlParser())->parse($xml);

        self::assertSame('KOR', $parsed->kindCode);
        self::assertSame('-3500', $parsed->items[0]->quantity);
        self::assertTrue($parsed->items[0]->beforeCorrection);
        self::assertSame(-500.0, array_sum(array_map(static fn ($i) => (float) $i->quantity, $parsed->items)));
        self::assertSame('7311000000-20260504-AAAAAA-BBBBBB-CC', $parsed->correctedInvoices[0]['ksefNumber']);
        self::assertSame('PET/7/2026', $parsed->correctedInvoices[0]['number']);
    }

    public function testParsesFaRr(): void
    {
        $xml = <<<'XML'
            <?xml version="1.0" encoding="UTF-8"?>
            <Faktura xmlns="http://crd.gov.pl/wzor/2026/03/06/14189/">
              <Naglowek><KodFormularza kodSystemowy="FA_RR (1)" wersjaSchemy="1-1E">FA_RR</KodFormularza></Naglowek>
              <Podmiot1><DaneIdentyfikacyjne><NIP>5841000001</NIP><Nazwa>Gospodarstwo Rolne Kowalscy</Nazwa></DaneIdentyfikacyjne></Podmiot1>
              <Podmiot2><DaneIdentyfikacyjne><NIP>1131000001</NIP><Nazwa>Spółdzielnia Mleczarska</Nazwa></DaneIdentyfikacyjne></Podmiot2>
              <FakturaRR>
                <KodWaluty>PLN</KodWaluty>
                <P_4B>2026-06-10</P_4B>
                <P_4C>RR/6/2026</P_4C>
                <P_11_1>10000.00</P_11_1>
                <P_11_2>700.00</P_11_2>
                <P_12_1>10700.00</P_12_1>
                <RodzajFaktury>VAT_RR</RodzajFaktury>
                <FakturaRRWiersz>
                  <NrWierszaFa>1</NrWierszaFa>
                  <P_5>Pszenica</P_5>
                  <P_6A>t</P_6A>
                  <P_6B>12.5</P_6B>
                  <P_7>800.00</P_7>
                  <P_8>10000.00</P_8>
                  <P_9>7</P_9>
                </FakturaRRWiersz>
              </FakturaRR>
            </Faktura>
            XML;

        $parsed = (new InvoiceXmlParser())->parse($xml);

        self::assertTrue($parsed->isRr());
        self::assertSame('RR/6/2026', $parsed->number);
        self::assertSame('5841000001', $parsed->seller->nip);
        self::assertSame('12.5', $parsed->items[0]->quantity);
        self::assertSame('10700.00', $parsed->gross);
    }

    public function testRejectsUnknownStructure(): void
    {
        $this->expectException(KsefException::class);
        (new InvoiceXmlParser())->parse('<Inny xmlns="urn:x"><A/></Inny>');
    }
}
