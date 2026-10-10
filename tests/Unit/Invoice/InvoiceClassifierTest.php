<?php

declare(strict_types=1);

namespace App\Tests\Unit\Invoice;

use App\Enum\InvoiceKind;
use App\Enum\InvoiceType;
use App\Invoice\InvoiceClassifier;
use App\Ksef\Dto\InvoiceMetadata;
use App\Ksef\SubjectType;
use PHPUnit\Framework\TestCase;

final class InvoiceClassifierTest extends TestCase
{
    private const string FARM = '5841000001';
    private const string SUPPLIER = '7311000000';

    public function testFarmAsBuyerIsCost(): void
    {
        $metadata = self::metadata(self::SUPPLIER, 'Nip', self::FARM);

        self::assertSame(InvoiceKind::Cost, (new InvoiceClassifier())->classify($metadata, self::FARM, SubjectType::Buyer));
    }

    public function testFarmAsSellerIsIncome(): void
    {
        $metadata = self::metadata(self::FARM, 'Nip', self::SUPPLIER);

        self::assertSame(InvoiceKind::Income, (new InvoiceClassifier())->classify($metadata, self::FARM, SubjectType::Seller));
    }

    public function testVatRrWhereFarmIsTheFarmerSupplierIsIncome(): void
    {
        $metadata = self::metadata(self::FARM, 'Nip', '1131000001', 'VatRr', 'FA_RR');

        self::assertSame(InvoiceKind::Income, (new InvoiceClassifier())->classify($metadata, self::FARM, SubjectType::Seller));
        self::assertSame(InvoiceType::Rr, InvoiceType::fromKsef('VatRr'));
    }

    public function testWithoutFarmNipFallsBackToQueriedRole(): void
    {
        $metadata = self::metadata(self::SUPPLIER, 'Nip', self::FARM);

        self::assertSame(InvoiceKind::Cost, (new InvoiceClassifier())->classify($metadata, null, SubjectType::Buyer));
        self::assertSame(InvoiceKind::Income, (new InvoiceClassifier())->classify($metadata, null, SubjectType::Seller));
    }

    public function testCounterparty(): void
    {
        $classifier = new InvoiceClassifier();

        $cost = $classifier->counterparty(self::metadata(self::SUPPLIER, 'Nip', self::FARM), InvoiceKind::Cost);
        self::assertSame(self::SUPPLIER, $cost['nip']);

        $foreign = $classifier->counterparty(self::metadata(self::FARM, 'VatUe', 'DE123456789'), InvoiceKind::Income);
        self::assertNull($foreign['nip']);
        self::assertSame('DE123456789', $foreign['identifier']);
    }

    public function testKsefTypeMapping(): void
    {
        self::assertSame(InvoiceType::Correction, InvoiceType::fromKsef('KorZal'));
        self::assertSame(InvoiceType::Correction, InvoiceType::fromKsef('KOR_VAT_RR'));
        self::assertSame(InvoiceType::Advance, InvoiceType::fromKsef('Zal'));
        self::assertSame(InvoiceType::Invoice, InvoiceType::fromKsef('Upr'));
    }

    private static function metadata(string $seller, string $buyerType, ?string $buyer, string $type = 'Vat', string $form = 'FA'): InvoiceMetadata
    {
        $date = new \DateTimeImmutable('2026-05-04');

        return new InvoiceMetadata('X', 'FV/1', $date, $date, $date, $seller, 'Sprzedawca', $buyerType, $buyer, 'Nabywca', '100.00', '23.00', '123.00', 'PLN', $type, $form);
    }
}
