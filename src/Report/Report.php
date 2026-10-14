<?php

declare(strict_types=1);

namespace App\Report;

/**
 * Gotowe zestawienie – ta sama treść trafia do XLSX i do PDF.
 * Typy kolumn: text, money, qty, int, date, percent (jak w XlsxExporter).
 */
final class Report
{
    /**
     * @param list<array{label: string, type?: string, width?: float}> $columns
     * @param list<list<mixed>>                                        $rows
     * @param list<mixed>|null                                         $totals
     * @param list<string>                                             $notes
     */
    public function __construct(
        public readonly string $title,
        public readonly string $subtitle,
        public readonly string $filename,
        public readonly array $columns,
        public readonly array $rows,
        public readonly ?array $totals = null,
        public readonly array $notes = [],
    ) {
    }
}
