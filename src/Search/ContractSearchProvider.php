<?php

declare(strict_types=1);

namespace App\Search;

use App\Repository\ContractRepository;
use App\Security\Permission;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

final class ContractSearchProvider implements SearchProvider
{
    public function __construct(
        private readonly ContractRepository $contracts,
        private readonly UrlGeneratorInterface $urls,
    ) {
    }

    public function group(): string
    {
        return 'Umowy';
    }

    public function permission(): string
    {
        return Permission::CONTRACT_VIEW;
    }

    public function search(string $query, int $limit): array
    {
        return array_map(
            fn ($contract): SearchResult => new SearchResult(
                $contract->getNumber(),
                $this->urls->generate('app_contract_show', ['id' => $contract->getId()]),
                $contract->getContractor()?->getDisplayName().' · '.$contract->getSubject(),
            ),
            $this->contracts->search($query, $limit),
        );
    }
}
