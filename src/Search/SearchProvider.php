<?php

declare(strict_types=1);

namespace App\Search;

use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

#[AutoconfigureTag]
interface SearchProvider
{
    public function group(): string;

    /** Uprawnienie wymagane do zobaczenia wyników (null = wszyscy zalogowani). */
    public function permission(): ?string;

    /**
     * @return list<SearchResult>
     */
    public function search(string $query, int $limit): array;
}
