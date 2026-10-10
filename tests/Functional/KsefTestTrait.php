<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Entity\Farm;
use App\Farm\FarmContext;
use App\Ksef\Fake\Fa3Builder;
use App\Ksef\Fake\FakeKsefClient;
use App\Ksef\KsefSynchronizer;
use App\Settings\SettingKey;
use App\Settings\Settings;
use App\Tests\Factory\FarmFactory;

/**
 * Wspólne kroki testów z symulatorem KSeF.
 */
trait KsefTestTrait
{
    protected const string FARM_NIP = FarmFactory::TEST_NIP;
    protected const string SUPPLIER_NIP = '7311000000';

    protected function configureFarm(): Farm
    {
        $farm = FarmFactory::testFarm();
        static::getContainer()->get(FarmContext::class)->set($farm);
        static::getContainer()->get(Settings::class)->set(SettingKey::KSEF_SYNC_FROM, '2026-01-01');

        return $farm;
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
