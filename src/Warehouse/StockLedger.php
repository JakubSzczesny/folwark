<?php

declare(strict_types=1);

namespace App\Warehouse;

use App\Audit\AuditLogger;
use App\Entity\Product;
use App\Entity\Stock;
use App\Entity\StockDocument;
use App\Entity\StockMovement;
use App\Entity\User;
use App\Entity\Warehouse;
use App\Enum\DocumentType;
use App\Repository\StockMovementRepository;
use App\Repository\StockRepository;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Zatwierdzanie i anulowanie dokumentów: ruchy magazynowe, stany, wycena średnią ważoną.
 * Stan ujemny jest dozwolony – zwracamy wtedy ostrzeżenie.
 */
final class StockLedger
{
    /** @var array<string, Stock> */
    private array $stocks = [];

    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly StockRepository $stockRepository,
        private readonly StockMovementRepository $movements,
        private readonly DocumentNumberer $numberer,
        private readonly AuditLogger $audit,
    ) {
    }

    /**
     * @return list<string> ostrzeżenia o stanach ujemnych
     */
    public function approve(StockDocument $document, ?User $user): array
    {
        if (!$document->isDraft()) {
            throw new \LogicException('Zatwierdzić można tylko szkic.');
        }

        $this->stocks = [];
        $touched = [];
        $this->em->wrapInTransaction(function () use ($document, $user, &$touched): void {
            $this->numberer->assign($document);
            $document->approve($user);
            foreach ($document->getItems() as $item) {
                $item->recalculate();
                $product = $item->getProduct() ?? throw new \LogicException('Pozycja bez towaru.');
                foreach ($this->movementsFor($document, $product, (float) $item->getQuantity(), $item->getNetValue()) as [$warehouse, $quantity, $value]) {
                    $this->record($document, $product, $warehouse, $quantity, $value);
                    $stock = $this->stock($product, $warehouse);
                    $touched[spl_object_id($stock)] = $stock;
                }
            }
            $this->em->flush();
            $this->audit->log('StockDocument', $document->getId(), 'approve', ['number' => $document->getNumber()], $user);
        });

        $warnings = [];
        foreach ($touched as $stock) {
            if ($stock->isNegative()) {
                $warnings[] = self::negativeWarning($stock->getProduct(), $stock->getWarehouse(), (float) $stock->getQuantity());
            }
        }

        return $warnings;
    }

    public function cancel(StockDocument $document, string $reason, ?User $user): void
    {
        if ($document->isCancelled()) {
            return;
        }

        $this->stocks = [];
        $this->em->wrapInTransaction(function () use ($document, $reason, $user): void {
            foreach ($this->movements->findForDocument($document) as $movement) {
                $this->stock($movement->getProduct(), $movement->getWarehouse())
                    ->apply(-(float) $movement->getQuantity(), -(float) $movement->getValue(), $movement->getDate());
                $this->em->remove($movement);
            }
            $document->cancel($reason);
            $this->em->flush();
            $this->audit->log('StockDocument', $document->getId(), 'cancel', ['number' => $document->getNumber(), 'reason' => $reason], $user);
        });
    }

    /**
     * „Popraw”: anuluje zatwierdzony dokument i zakłada szkic z tymi samymi danymi.
     */
    public function correct(StockDocument $document, ?User $user): StockDocument
    {
        $draft = $document->copyAsDraft();
        $draft->setCreatedBy($user);
        $this->cancel($document, 'Poprawiony – zastąpiony nowym dokumentem', $user);
        $this->em->persist($draft);
        $this->em->flush();

        return $draft;
    }

    /**
     * Ostrzeżenia przed zatwierdzeniem: które wydania zejdą poniżej zera.
     *
     * @return list<string>
     */
    public function preview(StockDocument $document): array
    {
        if (!$document->getType()->needsSource() || null === ($warehouse = $document->getSourceWarehouse())) {
            return [];
        }

        $needed = [];
        foreach ($document->getItems() as $item) {
            if (null !== ($product = $item->getProduct())) {
                $item->recalculate();
                $needed[$product->getId()] = [$product, ($needed[$product->getId()][1] ?? 0.0) + (float) $item->getQuantity()];
            }
        }

        $warnings = [];
        foreach ($needed as [$product, $quantity]) {
            $stock = $this->stockRepository->findOneBy(['product' => $product, 'warehouse' => $warehouse]);
            $after = (float) $stock?->getQuantity() - $quantity;
            if ($after < 0) {
                $warnings[] = self::negativeWarning($product, $warehouse, $after);
            }
        }

        return $warnings;
    }

    /**
     * @return list<array{Warehouse, float, float}>
     */
    private function movementsFor(StockDocument $document, Product $product, float $quantity, ?string $netValue): array
    {
        $source = $document->getSourceWarehouse();
        $target = $document->getTargetWarehouse();

        return match ($document->getType()) {
            DocumentType::Pz, DocumentType::Pw => [[$this->required($target), $quantity, null !== $netValue ? (float) $netValue : $this->issueValue($product, $this->required($target), $quantity)]],
            DocumentType::Wz, DocumentType::Rw => [[$this->required($source), -$quantity, -$this->issueValue($product, $this->required($source), $quantity)]],
            DocumentType::Mm => $this->transfer($product, $this->required($source), $this->required($target), $quantity),
            DocumentType::Inw => $this->inventory($product, $this->required($target), $quantity, $netValue),
        };
    }

    /**
     * @return list<array{Warehouse, float, float}>
     */
    private function transfer(Product $product, Warehouse $source, Warehouse $target, float $quantity): array
    {
        $value = $this->issueValue($product, $source, $quantity);

        return [[$source, -$quantity, -$value], [$target, $quantity, $value]];
    }

    /**
     * INW: ruch korygujący do stanu ze spisu.
     *
     * @return list<array{Warehouse, float, float}>
     */
    private function inventory(Product $product, Warehouse $warehouse, float $counted, ?string $netValue): array
    {
        $stock = $this->stock($product, $warehouse);
        $difference = round($counted - (float) $stock->getQuantity(), 4);
        if (0.0 === $difference) {
            return [];
        }

        if (0.0 === $counted) {
            $value = -(float) $stock->getValue();
        } elseif ($difference < 0) {
            $value = -$this->issueValue($product, $warehouse, -$difference);
        } else {
            $price = null !== $netValue && $counted > 0 ? (float) $netValue / $counted : $this->unitPrice($product, $warehouse);
            $value = round($difference * $price, 2);
        }

        return [[$warehouse, $difference, $value]];
    }

    private function issueValue(Product $product, Warehouse $warehouse, float $quantity): float
    {
        $stock = $this->stock($product, $warehouse);
        if ((float) $stock->getQuantity() > 0 && abs((float) $stock->getQuantity() - $quantity) < 0.00005) {
            return (float) $stock->getValue();
        }

        return round($quantity * $this->unitPrice($product, $warehouse), 2);
    }

    private function unitPrice(Product $product, Warehouse $warehouse): float
    {
        return $this->stock($product, $warehouse)->getAveragePrice() ?? (float) $product->getBookPrice();
    }

    private function record(StockDocument $document, Product $product, Warehouse $warehouse, float $quantity, float $value): void
    {
        $date = $document->getDate() ?? new \DateTimeImmutable('today');
        $this->stock($product, $warehouse)->apply($quantity, $value, $date);
        $this->em->persist(new StockMovement($document, $product, $warehouse, $date, number_format($quantity, 4, '.', ''), number_format($value, 2, '.', '')));
    }

    private function stock(Product $product, Warehouse $warehouse): Stock
    {
        $key = $product->getId().'-'.$warehouse->getId();
        if (!isset($this->stocks[$key])) {
            $stock = $this->stockRepository->findOneBy(['product' => $product, 'warehouse' => $warehouse]);
            if (null === $stock) {
                $stock = new Stock($product, $warehouse);
                $this->em->persist($stock);
            }
            $this->stocks[$key] = $stock;
        }

        return $this->stocks[$key];
    }

    private function required(?Warehouse $warehouse): Warehouse
    {
        return $warehouse ?? throw new \LogicException('Dokument bez wymaganego magazynu.');
    }

    private static function negativeWarning(Product $product, Warehouse $warehouse, float $quantity): string
    {
        return \sprintf('„%s” w magazynie %s: stan ujemny %s %s.', $product->getName(), $warehouse->getName(), number_format($quantity, 3, ',', ' '), $product->getUnitSymbol());
    }
}
