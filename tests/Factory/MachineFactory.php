<?php

declare(strict_types=1);

namespace App\Tests\Factory;

use App\Entity\Machine;
use Zenstruck\Foundry\Persistence\PersistentObjectFactory;

/**
 * @extends PersistentObjectFactory<Machine>
 */
final class MachineFactory extends PersistentObjectFactory
{
    public static function class(): string
    {
        return Machine::class;
    }

    protected function defaults(): array
    {
        return [
            'name' => 'Ciągnik '.self::faker()->unique()->numberBetween(1, 9999),
            'farm' => FarmFactory::testFarm(),
        ];
    }
}
