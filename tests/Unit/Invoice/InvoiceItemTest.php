<?php

declare(strict_types=1);

namespace App\Tests\Unit\Invoice;

use App\Entity\Invoice;
use App\Entity\InvoiceItem;
use App\Enum\InvoiceKind;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class InvoiceItemTest extends TestCase
{
    /**
     * @return iterable<string, array{?string, ?string, ?int, ?string}>
     */
    public static function rates(): iterable
    {
        yield '23%' => ['1000.00', '23', 23, '1230.00'];
        yield '8% z zaokrągleniem' => ['33.33', '8', 8, '36.00'];
        yield '0 KR' => ['100.00', '0 KR', 0, '100.00'];
        yield 'zwolnione' => ['100.00', 'zw', null, '100.00'];
        yield 'korekta in minus' => ['-500.00', '23', 23, '-615.00'];
        yield 'bez wartości' => [null, '23', 23, null];
    }

    #[DataProvider('rates')]
    public function testGrossValueFromNetAndRate(?string $net, ?string $rate, ?int $percent, ?string $gross): void
    {
        $invoice = new Invoice('X', 'FV/1', InvoiceKind::Cost, new \DateTimeImmutable('2026-05-01'));
        $item = (new InvoiceItem($invoice, 1, 'Pozycja'))->setValues('1', 'szt', $net, $net, $rate);

        self::assertSame($percent, $item->getVatPercent());
        self::assertSame($gross, $item->getGrossValue());
    }
}
