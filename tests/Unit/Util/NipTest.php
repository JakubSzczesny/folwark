<?php

declare(strict_types=1);

namespace App\Tests\Unit\Util;

use App\Util\Nip;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class NipTest extends TestCase
{
    /**
     * @return iterable<string, array{?string, ?string}>
     */
    public static function normalizeCases(): iterable
    {
        yield 'same cyfry' => ['5261040828', '5261040828'];
        yield 'z myślnikami' => ['526-104-08-28', '5261040828'];
        yield 'z prefiksem PL i spacjami' => ['PL 526 104 08 28', '5261040828'];
        yield 'za krótki' => ['52610408', null];
        yield 'null' => [null, null];
    }

    #[DataProvider('normalizeCases')]
    public function testNormalize(?string $input, ?string $expected): void
    {
        self::assertSame($expected, Nip::normalize($input));
    }

    public function testChecksum(): void
    {
        self::assertTrue(Nip::isValid('526-104-08-28'));
        self::assertFalse(Nip::isValid('5261040829'));
        self::assertFalse(Nip::isValid('abc'));
    }

    public function testFormat(): void
    {
        self::assertSame('526-104-08-28', Nip::format('5261040828'));
    }
}
