<?php

declare(strict_types=1);

namespace App\Contract;

use App\Entity\Contract;
use App\Entity\InvoiceItem;
use App\Repository\InvoiceItemRepository;

/**
 * Realizacja umowy: suma ilości z pozycji faktur powiązanych z umową, w jednostce umowy.
 * Gdy umowa ma towar, liczą się tylko pozycje przypisane do tego towaru.
 */
class ContractRealization
{
    /** Przeliczniki jednostek masy i objętości między sobą (do jednostki bazowej). */
    private const array STANDARD = [
        'kg' => ['mass', 1.0],
        't' => ['mass', 1000.0],
        'dt' => ['mass', 100.0],
        'q' => ['mass', 100.0],
        'g' => ['mass', 0.001],
        'l' => ['volume', 1.0],
        'm3' => ['volume', 1000.0],
        'hl' => ['volume', 100.0],
    ];

    public function __construct(
        private readonly InvoiceItemRepository $items,
    ) {
    }

    /**
     * @return array{quantity: float, target: ?float, percent: ?float, unit: ?string, invoices: int, skippedItems: int}
     */
    public function calculate(Contract $contract): array
    {
        $unit = $contract->getQuantityUnit()?->getSymbol();
        $quantity = 0.0;
        $skipped = 0;
        $invoices = [];

        $product = $contract->getProduct();
        foreach ($this->items->findForContract($contract) as $item) {
            if (null !== $product && $item->getProduct() !== $product) {
                continue;
            }
            $converted = null !== $product && null !== $item->getQuantityInProductUnit()
                ? $this->convertQuantity((float) $item->getQuantityInProductUnit(), $product->getUnitSymbol(), $unit)
                : $this->convert($item, $unit);
            if (null === $converted) {
                ++$skipped;
                continue;
            }
            $quantity += $converted;
            $invoices[$item->getInvoice()->getId()] = true;
        }

        $target = null !== $contract->getContractedQuantity() ? (float) $contract->getContractedQuantity() : null;

        return [
            'quantity' => $quantity,
            'target' => $target,
            'percent' => null !== $target && $target > 0 ? $quantity / $target * 100 : null,
            'unit' => $unit,
            'invoices' => \count($invoices),
            'skippedItems' => $skipped,
        ];
    }

    public static function normalizeUnit(?string $unit): ?string
    {
        if (null === $unit) {
            return null;
        }

        $unit = mb_strtolower(trim($unit, " .\t"));

        return match ($unit) {
            'kilogram', 'kilogramy', 'kilogramów', 'kg.' => 'kg',
            'tona', 'tony', 'ton', 'tona metryczna' => 't',
            'litr', 'litry', 'litrów', 'ltr', 'l.' => 'l',
            'm³', 'm^3' => 'm3',
            'szt', 'sztuka', 'sztuki', 'sztuk', 'szt.' => 'szt',
            default => $unit,
        };
    }

    private function convert(InvoiceItem $item, ?string $targetUnit): ?float
    {
        if (null === $item->getQuantity() || $item->isSkipped()) {
            return null;
        }

        return $this->convertQuantity((float) $item->getQuantity(), $item->getUnit(), $targetUnit);
    }

    private function convertQuantity(float $quantity, ?string $unit, ?string $targetUnit): ?float
    {
        $itemUnit = self::normalizeUnit($unit);
        $targetUnit = self::normalizeUnit($targetUnit);

        if (null === $targetUnit || $itemUnit === $targetUnit) {
            return $quantity;
        }

        $from = self::STANDARD[(string) $itemUnit] ?? null;
        $to = self::STANDARD[$targetUnit] ?? null;
        if (null !== $from && null !== $to && $from[0] === $to[0]) {
            return $quantity * $from[1] / $to[1];
        }

        return null;
    }
}
