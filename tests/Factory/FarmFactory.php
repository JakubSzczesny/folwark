<?php

declare(strict_types=1);

namespace App\Tests\Factory;

use App\Entity\Farm;
use Zenstruck\Foundry\Persistence\PersistentObjectFactory;

/**
 * @extends PersistentObjectFactory<Farm>
 */
final class FarmFactory extends PersistentObjectFactory
{
    public const string TEST_NAME = 'Gospodarstwo testowe';
    public const string TEST_NIP = '5841000001';

    public static function class(): string
    {
        return Farm::class;
    }

    /**
     * Gospodarstwo, do którego domyślnie trafiają dane i użytkownicy w testach.
     */
    public static function testFarm(): Farm
    {
        return self::findOrCreate(['name' => self::TEST_NAME, 'nip' => self::TEST_NIP]);
    }

    protected function defaults(): array
    {
        return [
            'name' => 'Gospodarstwo '.self::faker()->unique()->lastName(),
            'nip' => null,
        ];
    }
}
