<?php

declare(strict_types=1);

namespace App\Search;

use App\Repository\ContractorRepository;
use App\Security\Permission;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

final class ContractorSearchProvider implements SearchProvider
{
    public function __construct(
        private readonly ContractorRepository $contractors,
        private readonly UrlGeneratorInterface $urls,
    ) {
    }

    public function group(): string
    {
        return 'Kontrahenci';
    }

    public function permission(): string
    {
        return Permission::CONTRACTOR_VIEW;
    }

    public function search(string $query, int $limit): array
    {
        return array_map(
            fn ($contractor): SearchResult => new SearchResult(
                $contractor->getName(),
                $this->urls->generate('app_contractor_show', ['id' => $contractor->getId()]),
                trim(($contractor->getNip() ? 'NIP '.$contractor->getNip() : '').' '.($contractor->getCity() ?? '')),
            ),
            $this->contractors->search($query, $limit),
        );
    }
}
