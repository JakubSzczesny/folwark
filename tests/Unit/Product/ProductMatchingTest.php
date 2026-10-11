<?php

declare(strict_types=1);

namespace App\Tests\Unit\Product;

use App\Entity\Product;
use App\Entity\ProductUnitConversion;
use App\Entity\Unit;
use App\Product\ProductMatcher;
use App\Product\UnitParser;
use PHPUnit\Framework\TestCase;

final class ProductMatchingTest extends TestCase
{
    public function testSameUnitAndPhysicalConversions(): void
    {
        $parser = new UnitParser();

        self::assertSame(1.0, $parser->factor('Olej napędowy ON', 'l', self::product('l')));
        self::assertSame(1.0, $parser->factor('Olej napędowy ON', 'L.', self::product('l')), 'Wielkość liter i kropki nie mają znaczenia');
        self::assertSame(1000.0, $parser->factor('Saletra amonowa 34%', 't', self::product('kg')));
        self::assertSame(0.001, $parser->factor('Pszenica', 'kg', self::product('t')));
    }

    public function testPackageContentIsReadFromItemName(): void
    {
        $parser = new UnitParser();

        self::assertSame(5.0, $parser->factor('Olej silnikowy 15W40 5L', 'szt', self::product('l')), '„5L” w nazwie – kanister 5 l');
        self::assertSame(25.0, $parser->factor('Dodatek mineralny Bovi-Min worek 25kg', 'szt.', self::product('kg')));
        self::assertSame(20.0, $parser->factor('Rękawice nitrylowe opak. 20 szt.', 'op', self::product('szt')));
        self::assertSame(0.5, $parser->factor('Polifoska big bag 500kg', 'szt', self::product('t')), '500 kg = 0,5 t');
        self::assertNull($parser->factor('Smar uniwersalny', 'szt', self::product('kg')), 'Bez informacji w nazwie nie zgadujemy');
    }

    public function testProductConversionWins(): void
    {
        $product = self::product('kg');
        $bale = new Unit('bela', 'bela');
        $product->addConversion((new ProductUnitConversion())->setUnit($bale)->setFactor('450'));

        self::assertSame(450.0, (new UnitParser())->factor('Sianokiszonka w belach', 'bela', $product));
    }

    public function testMatcherSuggestsBySimilarNameOrCatalogNumber(): void
    {
        $diesel = (new Product())->setName('Olej napędowy');
        $filter = (new Product())->setName('Filtr oleju silnika JD')->setCatalogNumber('RE504836');
        $mix = (new Product())->setName('Mieszanka paszowa MLEKO 18%');
        $matcher = new ProductMatcher();

        self::assertSame($diesel, $matcher->suggest('Olej napędowy ON grzewczy', [$diesel, $filter, $mix]));
        self::assertSame($filter, $matcher->suggest('Filtr RE 504836 oryginał', [$diesel, $filter, $mix]), 'Numer katalogowy w nazwie pozycji');
        self::assertSame($mix, $matcher->suggest('MIESZANKA PASZOWA mleko 18% luzem', [$diesel, $filter, $mix]));
        self::assertNull($matcher->suggest('Robocizna serwisowa', [$diesel, $filter, $mix]));
    }

    public function testNameKeyIgnoresCaseAndPunctuation(): void
    {
        self::assertSame(ProductMatcher::key('Olej silnikowy 15W40, 5L.'), ProductMatcher::key('  OLEJ  silnikowy 15w40 5l '));
    }

    private static function product(string $unit): Product
    {
        return (new Product())->setName('Towar')->setBaseUnit(new Unit($unit, $unit));
    }
}
