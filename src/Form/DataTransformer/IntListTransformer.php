<?php

declare(strict_types=1);

namespace App\Form\DataTransformer;

use Symfony\Component\Form\DataTransformerInterface;
use Symfony\Component\Form\Exception\TransformationFailedException;

/**
 * "30, 14, 3" <-> [30, 14, 3].
 *
 * @implements DataTransformerInterface<list<int>, string>
 */
final class IntListTransformer implements DataTransformerInterface
{
    public function transform(mixed $value): string
    {
        return \is_array($value) ? implode(', ', $value) : '';
    }

    public function reverseTransform(mixed $value): array
    {
        if (null === $value || '' === trim((string) $value)) {
            return [];
        }

        $parts = preg_split('/[\s,;]+/', trim((string) $value)) ?: [];
        $result = [];
        foreach ($parts as $part) {
            if (!ctype_digit($part)) {
                throw new TransformationFailedException('Wpisz liczby dni oddzielone przecinkami, np. 30, 14, 3.');
            }
            $result[] = (int) $part;
        }
        rsort($result);

        return array_values(array_unique($result));
    }
}
