<?php

declare(strict_types=1);

namespace App\Search;

use App\Entity\StockDocument;
use App\Repository\StockDocumentRepository;
use App\Security\Permission;
use App\Twig\FormatExtension;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

final class DocumentSearchProvider implements SearchProvider
{
    public function __construct(
        private readonly StockDocumentRepository $documents,
        private readonly UrlGeneratorInterface $urls,
    ) {
    }

    public function group(): string
    {
        return 'Dokumenty magazynowe';
    }

    public function permission(): string
    {
        return Permission::DOCUMENT_VIEW;
    }

    public function search(string $query, int $limit): array
    {
        return array_map(
            fn (StockDocument $document): SearchResult => new SearchResult(
                $document->getLabel(),
                $this->urls->generate('app_document_show', ['id' => $document->getId()]),
                implode(' · ', array_filter([FormatExtension::date($document->getDate()), $document->getContractor()?->getDisplayName(), $document->getStatus()->label()])),
            ),
            $this->documents->search($query, $limit),
        );
    }
}
