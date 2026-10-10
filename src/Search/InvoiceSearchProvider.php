<?php

declare(strict_types=1);

namespace App\Search;

use App\Repository\InvoiceRepository;
use App\Security\Permission;
use App\Twig\FormatExtension;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

final class InvoiceSearchProvider implements SearchProvider
{
    public function __construct(
        private readonly InvoiceRepository $invoices,
        private readonly UrlGeneratorInterface $urls,
    ) {
    }

    public function group(): string
    {
        return 'Faktury';
    }

    public function permission(): string
    {
        return Permission::INVOICE_VIEW;
    }

    public function search(string $query, int $limit): array
    {
        return array_map(
            fn ($invoice): SearchResult => new SearchResult(
                $invoice->getNumber(),
                $this->urls->generate('app_invoice_show', ['id' => $invoice->getId()]),
                $invoice->getContractorLabel().' · '.FormatExtension::date($invoice->getIssueDate()),
            ),
            $this->invoices->search($query, $limit),
        );
    }
}
