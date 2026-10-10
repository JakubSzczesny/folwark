<?php

declare(strict_types=1);

namespace App\Tests\Factory;

use App\Entity\Invoice;
use App\Enum\InvoiceKind;
use Zenstruck\Foundry\Persistence\PersistentObjectFactory;

/**
 * @extends PersistentObjectFactory<Invoice>
 */
final class InvoiceFactory extends PersistentObjectFactory
{
    public static function class(): string
    {
        return Invoice::class;
    }

    protected function defaults(): array
    {
        return [
            'ksefNumber' => '7311000000-20260101-'.strtoupper(self::faker()->unique()->regexify('[0-9A-F]{6}-[0-9A-F]{6}-[0-9A-F]{2}')),
            'number' => 'FV/'.self::faker()->unique()->numberBetween(1, 99999).'/2026',
            'kind' => InvoiceKind::Cost,
            'farm' => FarmFactory::testFarm(),
            'issueDate' => \DateTimeImmutable::createFromMutable(self::faker()->dateTimeBetween('-6 months'))->setTime(0, 0),
        ];
    }

    protected function initialize(): static
    {
        return $this->afterInstantiate(static function (Invoice $invoice): void {
            if ('0.00' === $invoice->getGross()) {
                $invoice->setAmounts('1000.00', '230.00', '1230.00');
            }
        });
    }
}
