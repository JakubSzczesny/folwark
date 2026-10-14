<?php

declare(strict_types=1);

namespace App\Warehouse;

use App\Entity\StockDocument;
use App\Repository\StockDocumentRepository;
use App\Settings\SettingKey;
use App\Settings\Settings;

/**
 * Numer wg wzorca z ustawień, np. {TYP}/{RRRR}/{MM}/{NNNN} → PZ/2026/10/0001. Numeracja miesięczna, osobna dla typu.
 */
final class DocumentNumberer
{
    public function __construct(
        private readonly StockDocumentRepository $documents,
        private readonly Settings $settings,
    ) {
    }

    public function assign(StockDocument $document): void
    {
        $date = $document->getDate() ?? new \DateTimeImmutable('today');
        $sequence = $this->documents->nextSequence($document->getType(), $date);
        $document->setNumber(self::format($this->settings->string(SettingKey::WAREHOUSE_NUMBER_PATTERN) ?: '{TYP}/{RRRR}/{MM}/{NNNN}', $document, $sequence), $sequence);
    }

    public static function format(string $pattern, StockDocument $document, int $sequence): string
    {
        $date = $document->getDate() ?? new \DateTimeImmutable('today');
        $number = strtr($pattern, [
            '{TYP}' => $document->getType()->value,
            '{RRRR}' => $date->format('Y'),
            '{RR}' => $date->format('y'),
            '{MM}' => $date->format('m'),
        ]);

        return (string) preg_replace_callback('/\{(N+)\}/', static fn (array $m): string => str_pad((string) $sequence, \strlen($m[1]), '0', \STR_PAD_LEFT), $number);
    }
}
