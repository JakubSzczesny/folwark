<?php

declare(strict_types=1);

namespace App\Tests\Factory;

use App\Entity\Contract;
use App\Enum\ContractKind;
use App\Enum\ContractStatus;
use Zenstruck\Foundry\Persistence\PersistentObjectFactory;

/**
 * @extends PersistentObjectFactory<Contract>
 */
final class ContractFactory extends PersistentObjectFactory
{
    public static function class(): string
    {
        return Contract::class;
    }

    protected function defaults(): array
    {
        return [
            'contractor' => ContractorFactory::new(),
            'number' => 'UM/'.self::faker()->unique()->numberBetween(1, 999).'/2026',
            'subject' => 'Sprzedaż pszenicy',
            'kind' => ContractKind::Sale,
            'status' => ContractStatus::Active,
        ];
    }
}
