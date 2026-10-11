<?php

declare(strict_types=1);

namespace App\Search;

use App\Entity\Machine;
use App\Repository\MachineRepository;
use App\Security\Permission;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

final class MachineSearchProvider implements SearchProvider
{
    public function __construct(
        private readonly MachineRepository $machines,
        private readonly UrlGeneratorInterface $urls,
    ) {
    }

    public function group(): string
    {
        return 'Maszyny';
    }

    public function permission(): string
    {
        return Permission::MACHINE_VIEW;
    }

    public function search(string $query, int $limit): array
    {
        return array_map(
            fn (Machine $machine): SearchResult => new SearchResult(
                $machine->getName(),
                $this->urls->generate('app_machine_show', ['id' => $machine->getId()]),
                implode(' · ', array_filter([$machine->getRegistrationNumber(), $machine->getCategory()?->getName(), $machine->isActive() ? null : 'wycofana'])),
            ),
            $this->machines->search($query, $limit),
        );
    }
}
