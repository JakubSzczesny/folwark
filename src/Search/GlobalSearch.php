<?php

declare(strict_types=1);

namespace App\Search;

use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;

final class GlobalSearch
{
    public const int MIN_LENGTH = 2;

    /**
     * @param iterable<SearchProvider> $providers
     */
    public function __construct(
        #[AutowireIterator(SearchProvider::class)]
        private readonly iterable $providers,
        private readonly AuthorizationCheckerInterface $auth,
    ) {
    }

    /**
     * @return list<array{group: string, results: list<SearchResult>}>
     */
    public function search(string $query, int $limitPerGroup = 5): array
    {
        $query = trim($query);
        if (mb_strlen($query) < self::MIN_LENGTH) {
            return [];
        }

        $groups = [];
        foreach ($this->providers as $provider) {
            $permission = $provider->permission();
            if (null !== $permission && !$this->auth->isGranted($permission)) {
                continue;
            }

            $results = $provider->search($query, $limitPerGroup);
            if ([] !== $results) {
                $groups[] = ['group' => $provider->group(), 'results' => $results];
            }
        }

        return $groups;
    }

    public static function likePattern(string $query): string
    {
        return '%'.addcslashes(mb_strtolower($query), '%_\\').'%';
    }
}
