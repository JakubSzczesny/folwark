<?php

declare(strict_types=1);

namespace App\Dashboard;

use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;

final class AttentionList
{
    public const int PER_PAGE = 8;

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
     * Jedna strona listy posortowanej wg pilności.
     *
     * @return array{items: list<AttentionItem>, total: int, page: int, pages: int}
     */
    public function build(int $page = 1, ?\DateTimeImmutable $today = null): array
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

        $pages = max(1, (int) ceil(\count($items) / self::PER_PAGE));
        $page = min(max(1, $page), $pages);

        return [
            'items' => \array_slice($items, ($page - 1) * self::PER_PAGE, self::PER_PAGE),
            'total' => \count($items),
            'page' => $page,
            'pages' => $pages,
        ];
    }
}
