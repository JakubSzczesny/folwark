<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Ksef\Fake\Fa3Builder;
use App\Ksef\Fake\FakeKsefClient;
use App\Ksef\KsefSynchronizer;
use App\Settings\SettingKey;
use App\Settings\Settings;

/**
 * Wspólne kroki testów z symulatorem KSeF.
 */
trait KsefTestTrait
{
    protected const string FARM_NIP = '5841000001';
    protected const string SUPPLIER_NIP = '7311000000';

    protected function configureFarm(): void
    {
        $settings = static::getContainer()->get(Settings::class);
        $settings->set(SettingKey::FARM_NIP, self::FARM_NIP, false);
        $settings->set(SettingKey::FARM_NAME, 'Gospodarstwo testowe', false);
        $settings->set(SettingKey::KSEF_SYNC_FROM, '2026-01-01');
    }

    protected function fakeKsef(): FakeKsefClient
    {
        return static::getContainer()->get(FakeKsefClient::class);
    }

    protected function syncKsef(): void
    {
        static::getContainer()->get(KsefSynchronizer::class)->sync(true);
    }

    protected function costInvoice(string $number, string $date, ?string $sellerNip = null, string $sellerName = 'PETROMEX Sp. z o.o.', ?string $address = null): Fa3Builder
    {
        $builder = Fa3Builder::invoice($sellerNip ?? self::SUPPLIER_NIP, $sellerName, self::FARM_NIP, 'Gospodarstwo testowe', $number, new \DateTimeImmutable($date))
            ->paymentDue((new \DateTimeImmutable($date))->modify('+14 days'));
        if (null !== $address) {
            $builder->sellerAddress($address, '87-100 Toruń');
        }

        return $builder;
    }

    /**
     * Faktura trafia do symulatora z datą trwałego zapisu „teraz” – tak jak nowa faktura w KSeF.
     */
    protected function addInvoice(Fa3Builder $builder): string
    {
        return $this->fakeKsef()->add($builder->toXml());
    }
}
