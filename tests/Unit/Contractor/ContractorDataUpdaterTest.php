<?php

declare(strict_types=1);

namespace App\Tests\Unit\Contractor;

use App\Contractor\ContractorDataUpdater;
use App\Entity\Contractor;
use App\Entity\Invoice;
use App\Enum\DataSource;
use App\Enum\InvoiceKind;
use PHPUnit\Framework\TestCase;

final class ContractorDataUpdaterTest extends TestCase
{
    public function testNewerInvoiceUpdatesOlderDoesNot(): void
    {
        $updater = new ContractorDataUpdater();
        $contractor = (new Contractor())->setNip('7311000000')->setName('Petromex')->setCity('Toruń');
        $current = self::invoice('2026-05-10');
        $contractor->setDataFromInvoice($current);

        self::assertTrue($updater->acceptsInvoiceData($contractor, self::invoice('2026-06-01')));
        self::assertTrue($updater->acceptsInvoiceData($contractor, self::invoice('2026-05-10')), 'Ta sama data wystawienia też aktualizuje');
        self::assertFalse($updater->acceptsInvoiceData($contractor, self::invoice('2026-04-30')));
    }

    public function testLockPreventsUpdates(): void
    {
        $updater = new ContractorDataUpdater();
        $contractor = (new Contractor())->setNip('7311000000')->setName('Petromex')->setUpdateLocked(true);

        self::assertFalse($updater->acceptsInvoiceData($contractor, self::invoice('2030-01-01')));
    }

    public function testApplyRecordsChangedFieldsOnly(): void
    {
        $updater = new ContractorDataUpdater();
        $contractor = (new Contractor())->setNip('7311000000')->setName('Petromex')->setCity('Toruń');
        $invoice = self::invoice('2026-06-01');

        $history = $updater->apply($contractor, ['name' => 'PETROMEX Sp. z o.o.', 'city' => 'Toruń', 'street' => null], DataSource::KsefXml, $invoice);

        self::assertCount(1, $history);
        self::assertSame('name', $history[0]->getField());
        self::assertSame('Petromex', $history[0]->getOldValue());
        self::assertSame('PETROMEX Sp. z o.o.', $history[0]->getNewValue());
        self::assertSame($invoice, $history[0]->getInvoice());
        self::assertSame('PETROMEX Sp. z o.o.', $contractor->getName());
        self::assertSame(DataSource::KsefXml, $contractor->getDataSource());
    }

    public function testManualEditOfAddressSetsLock(): void
    {
        $updater = new ContractorDataUpdater();
        $contractor = (new Contractor())->setNip('7311000000')->setName('Petromex')->setCity('Toruń');
        $before = $updater->snapshot($contractor, ['name', 'city', 'phone']);

        $contractor->setPhone('600 100 200');
        $history = $updater->recordManualEdit($contractor, $before, null);
        self::assertFalse($contractor->isUpdateLocked(), 'Zmiana telefonu nie blokuje aktualizacji');
        self::assertCount(1, $history);

        $before = $updater->snapshot($contractor, ['name', 'city', 'phone']);
        $contractor->setCity('Bydgoszcz');
        $history = $updater->recordManualEdit($contractor, $before, null);
        self::assertTrue($contractor->isUpdateLocked());
        self::assertSame(['city', 'updateLocked'], array_map(static fn ($h) => $h->getField(), $history));
    }

    private static function invoice(string $date): Invoice
    {
        return new Invoice(uniqid('ksef', true), 'FV/1', InvoiceKind::Cost, new \DateTimeImmutable($date));
    }
}
