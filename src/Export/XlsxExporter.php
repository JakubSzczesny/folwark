<?php

declare(strict_types=1);

namespace App\Export;

use OpenSpout\Common\Entity\Cell;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Common\Entity\Style\Style;
use OpenSpout\Writer\XLSX\Options;
use OpenSpout\Writer\XLSX\Writer;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;

/**
 * Eksport tabel do XLSX. Liczby i daty zapisujemy jako wartości (nie tekst), więc w Excelu
 * można je od razu sumować i sortować; format wyświetlania dostosuje się do ustawień regionalnych.
 */
final class XlsxExporter
{
    private const array FORMATS = [
        'money' => '#,##0.00',
        'qty' => '#,##0.###',
        'int' => '0',
        'date' => 'dd.mm.yyyy',
        'percent' => '0.0%',
    ];

    /**
     * @param list<array{label: string, type?: string, width?: float}> $columns
     * @param iterable<list<mixed>>                                    $rows
     * @param list<mixed>|null                                         $totals
     */
    public function response(string $filename, string $sheetTitle, array $columns, iterable $rows, ?array $totals = null): BinaryFileResponse
    {
        $path = tempnam(sys_get_temp_dir(), 'xlsx');
        if (false === $path) {
            throw new \RuntimeException('Nie można utworzyć pliku tymczasowego.');
        }

        $options = new Options();
        foreach ($columns as $index => $column) {
            $options->setColumnWidth($column['width'] ?? self::defaultWidth($column['type'] ?? 'text'), $index + 1);
        }

        $writer = new Writer($options);
        $writer->openToFile($path);
        $writer->getCurrentSheet()->setName(mb_substr($sheetTitle, 0, 31));

        $header = (new Style())->withFontBold(true)->withBackgroundColor('EEF0F2');
        $writer->addRow(Row::fromValuesWithStyle(array_column($columns, 'label'), $header));

        $styles = [];
        foreach ($columns as $index => $column) {
            $format = self::FORMATS[$column['type'] ?? 'text'] ?? null;
            $styles[$index] = null !== $format ? (new Style())->withFormat($format) : null;
        }

        foreach ($rows as $row) {
            $writer->addRow($this->row($row, $styles));
        }

        if (null !== $totals) {
            $bold = [];
            foreach ($styles as $index => $style) {
                $bold[$index] = ($style ?? new Style())->withFontBold(true);
            }
            $writer->addRow($this->row($totals, $bold));
        }

        $writer->close();

        $response = new BinaryFileResponse($path);
        $response->headers->set('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        $response->setContentDisposition(ResponseHeaderBag::DISPOSITION_ATTACHMENT, $filename, self::asciiName($filename));
        $response->deleteFileAfterSend();

        return $response;
    }

    /**
     * @param list<mixed>            $values
     * @param array<int, Style|null> $styles
     */
    private function row(array $values, array $styles): Row
    {
        $cells = [];
        foreach (array_values($values) as $index => $value) {
            if (\is_string($value) && is_numeric($value) && null !== ($styles[$index] ?? null)) {
                $value = (float) $value;
            }
            if (!\is_scalar($value) && !$value instanceof \DateTimeInterface && null !== $value) {
                $value = (string) $value;
            }
            $cells[] = Cell::fromValue($value, $styles[$index] ?? null);
        }

        return new Row($cells);
    }

    private static function defaultWidth(string $type): float
    {
        return match ($type) {
            'money', 'qty' => 14,
            'date' => 12,
            'int' => 8,
            default => 28,
        };
    }

    private static function asciiName(string $filename): string
    {
        $ascii = iconv('UTF-8', 'ASCII//TRANSLIT', $filename);

        return false === $ascii ? 'eksport.xlsx' : preg_replace('/[^A-Za-z0-9._-]+/', '_', $ascii) ?? 'eksport.xlsx';
    }
}
