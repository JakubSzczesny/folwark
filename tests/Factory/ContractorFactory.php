<?php

declare(strict_types=1);

namespace App\Tests\Factory;

use App\Entity\Contractor;
use App\Enum\ContractorKind;
use Zenstruck\Foundry\Persistence\PersistentObjectFactory;

/**
 * @extends PersistentObjectFactory<Contractor>
 */
final class ContractorFactory extends PersistentObjectFactory
{
    public static function class(): string
    {
        return Contractor::class;
    }

    protected function defaults(): array
    {
        return [
            'name' => self::faker()->company(),
            'nip' => null,
            'city' => self::faker()->city(),
            'kind' => ContractorKind::Supplier,
            'active' => true,
        ];
    }
}
