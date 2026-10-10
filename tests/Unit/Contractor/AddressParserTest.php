<?php

declare(strict_types=1);

namespace App\Tests\Unit\Contractor;

use App\Contractor\AddressParser;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class AddressParserTest extends TestCase
{
    /**
     * @return iterable<string, array{?string, ?string, array{street: ?string, postalCode: ?string, city: ?string}}>
     */
    public static function cases(): iterable
    {
        yield 'dwie linie z faktury' => ['ul. Paliwowa 12', '87-100 Toruń', ['street' => 'ul. Paliwowa 12', 'postalCode' => '87-100', 'city' => 'Toruń']];
        yield 'jedna linia z Białej listy' => ['UL. POLNA 50, 00-635 WARSZAWA', null, ['street' => 'UL. POLNA 50', 'postalCode' => '00-635', 'city' => 'Warszawa']];
        yield 'wieś bez ulicy' => ['Strzelce 1', '99-307 Strzelce', ['street' => 'Strzelce 1', 'postalCode' => '99-307', 'city' => 'Strzelce']];
        yield 'bez kodu' => ['Hauptstraße 5', 'Berlin', ['street' => 'Hauptstraße 5', 'postalCode' => null, 'city' => 'Berlin']];
        yield 'pusto' => [null, null, ['street' => null, 'postalCode' => null, 'city' => null]];
    }

    /**
     * @param array{street: ?string, postalCode: ?string, city: ?string} $expected
     */
    #[DataProvider('cases')]
    public function testParse(?string $line1, ?string $line2, array $expected): void
    {
        self::assertSame($expected, AddressParser::parse($line1, $line2));
    }
}
