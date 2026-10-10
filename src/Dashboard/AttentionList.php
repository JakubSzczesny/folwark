<?php

declare(strict_types=1);

namespace App\Dashboard;

use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;

final class AttentionList
{
    public const int LIMIT = 10;

    /**
     * @param iterable<AttentionSource> $sources
     */
    public function __construct(
        #[AutowireIterator(AttentionSource::class)]
        private readonly iterable $sources,
        private readonly AuthorizationCheckerInterface $auth,
    ) {
    }

    /**
     * @return array{items: list<AttentionItem>, total: int}
     */
    public function build(?\DateTimeImmutable $today = null): array
    {
        $today ??= new \DateTimeImmutable('today');
        $items = [];
        foreach ($this->sources as $source) {
            $permission = $source->permission();
            if (null !== $permission && !$this->auth->isGranted($permission)) {
                continue;
            }
            array_push($items, ...$source->items($today));
        }

        usort($items, static fn (AttentionItem $a, AttentionItem $b): int => [$a->urgency, $a->title] <=> [$b->urgency, $b->title]);

        return ['items' => \array_slice($items, 0, self::LIMIT), 'total' => \count($items)];
    }
}
