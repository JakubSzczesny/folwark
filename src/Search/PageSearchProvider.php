<?php

declare(strict_types=1);

namespace App\Search;

use App\Ui\Navigation;

/**
 * Pozwala przejść do dowolnego ekranu z menu przez wyszukiwarkę (Ctrl+K).
 */
final class PageSearchProvider implements SearchProvider
{
    public function __construct(
        private readonly Navigation $navigation,
    ) {
    }

    public function group(): string
    {
        return 'Ekrany';
    }

    public function permission(): ?string
    {
        return null;
    }

    public function search(string $query, int $limit): array
    {
        $needle = mb_strtolower($query);
        $results = [];
        foreach ($this->navigation->items() as $item) {
            foreach ([$item, ...$item['children']] as $entry) {
                if (str_contains(mb_strtolower($entry['label']), $needle)) {
                    $results[] = new SearchResult($entry['label'], $entry['url'], $entry === $item ? null : $item['label']);
                }
            }
        }

        return \array_slice($results, 0, $limit);
    }
}
