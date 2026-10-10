<?php

declare(strict_types=1);

namespace App\Tests\Unit\Form;

use App\Form\DataTransformer\IntListTransformer;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Form\Exception\TransformationFailedException;

final class IntListTransformerTest extends TestCase
{
    public function testRoundTrip(): void
    {
        $transformer = new IntListTransformer();

        self::assertSame('30, 14, 3', $transformer->transform([30, 14, 3]));
        self::assertSame([30, 14, 3], $transformer->reverseTransform('3; 30 14,14'));
        self::assertSame([], $transformer->reverseTransform(''));
    }

    public function testRejectsText(): void
    {
        $this->expectException(TransformationFailedException::class);
        (new IntListTransformer())->reverseTransform('30, dwa');
    }
}
