<?php

declare(strict_types=1);

namespace App\Product;

use App\Entity\Invoice;
use App\Entity\InvoiceItem;
use App\Entity\ItemMapping;
use App\Entity\User;
use App\Enum\InvoiceStatus;
use App\Invoice\InvoiceActions;
use App\Order\OrderPlanner;
use App\Repository\ItemMappingRepository;
use App\Repository\ProductRepository;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Przypisanie pozycji faktury do towarów z kartoteki – podstawa historii cen i przewidywania zamówień.
 * Raz przypisana nazwa pozycji od danego dostawcy jest zapamiętywana i przy kolejnej fakturze wypełnia się sama.
 */
final class ItemAssigner
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly ItemMappingRepository $mappings,
        private readonly ProductRepository $products,
        private readonly ProductMatcher $matcher,
        private readonly UnitParser $units,
        private readonly InvoiceActions $actions,
        private readonly OrderPlanner $planner,
    ) {
    }

    /**
     * Wiersze ekranu przypisywania: obecny stan, zapamiętane mapowanie albo propozycja.
     *
     * @return list<AssignmentRow>
     */
    public function rows(Invoice $invoice): array
    {
        $items = self::items($invoice);
        $remembered = $this->mappings->forContractor($invoice->getContractor());
        $elsewhere = $this->mappings->findAnyByKeys(array_values(array_unique(array_map(static fn (InvoiceItem $i): string => ProductMatcher::key($i->getName()), $items))));
        $catalog = null;

        $rows = [];
        foreach ($items as $item) {
            $key = ProductMatcher::key($item->getName());
            $row = new AssignmentRow($item, costCenter: $item->getCostCenter());

            if (null !== $item->getProduct() || $item->isSkipped()) {
                $row->product = $item->getProduct();
                $row->skip = $item->isSkipped();
                $row->factor = self::currentFactor($item);
                $row->source = AssignmentRow::ASSIGNED;
            } elseif (isset($remembered[$key])) {
                $this->fromMapping($row, $remembered[$key]);
                $row->source = AssignmentRow::REMEMBERED;
            } elseif (isset($elsewhere[$key])) {
                $row->product = $elsewhere[$key]->getProduct();
                $row->source = AssignmentRow::PROPOSAL;
            } else {
                $catalog ??= $this->products->findActive();
                $row->product = $this->matcher->suggest($item->getName(), $catalog);
                $row->source = null !== $row->product ? AssignmentRow::PROPOSAL : null;
            }

            if (null !== $row->product && null === $row->factor) {
                $row->factor = $this->units->factor($item->getName(), $item->getUnit(), $row->product);
            }
            $rows[] = $row;
        }

        return $rows;
    }

    /**
     * Zapisuje przypisanie, zapamiętuje je dla kolejnych faktur dostawcy i przelicza zamówienia.
     *
     * @param list<AssignmentRow> $rows
     */
    public function save(Invoice $invoice, array $rows, ?User $user): void
    {
        $remembered = $this->mappings->forContractor($invoice->getContractor());

        foreach ($rows as $row) {
            $this->apply($row);

            $key = ProductMatcher::key($row->item->getName());
            $mapping = $remembered[$key] ?? null;
            if (null === $mapping) {
                $mapping = new ItemMapping($invoice->getContractor(), $key);
                $this->em->persist($mapping);
                $remembered[$key] = $mapping;
            }
            $mapping->remember($row->product, $row->skip, null !== $row->factor ? number_format($row->factor, 6, '.', '') : null, $row->costCenter);
        }

        $assigned = \count(array_filter($rows, static fn (AssignmentRow $r): bool => null !== $r->product));
        $this->actions->changeStatus($invoice, InvoiceStatus::GoodsAssigned, ['items' => $assigned]);
        $invoice->markRead($user);
        $this->em->flush();

        $this->planner->recalculate();
    }

    /**
     * Przypisanie przy synchronizacji: tylko gdy każda pozycja ma zapamiętane mapowanie.
     * Faktura dostaje status „towary przypisane”, ale zostaje nieprzeczytana.
     */
    public function autoAssign(Invoice $invoice): bool
    {
        $items = self::items($invoice);
        if (!$invoice->isCost() || [] === $items || $invoice->getStatus()->isProcessed()) {
            return false;
        }

        $remembered = $this->mappings->forContractor($invoice->getContractor());
        $rows = [];
        foreach ($items as $item) {
            $mapping = $remembered[ProductMatcher::key($item->getName())] ?? null;
            if (null === $mapping || (!$mapping->isSkip() && null === $mapping->getProduct())) {
                return false;
            }
            $row = new AssignmentRow($item, source: AssignmentRow::REMEMBERED);
            $this->fromMapping($row, $mapping);
            if (null !== $row->product && null === $row->factor) {
                $row->factor = $this->units->factor($item->getName(), $item->getUnit(), $row->product);
            }
            $rows[] = $row;
        }

        foreach ($rows as $row) {
            $this->apply($row);
        }
        $this->actions->changeStatus($invoice, InvoiceStatus::GoodsAssigned, ['auto' => true]);

        return true;
    }

    private function apply(AssignmentRow $row): void
    {
        if ($row->skip) {
            $row->item->setSkipped(true);
        } else {
            $row->item->assignProduct($row->product, $row->factor);
        }
        $row->item->setCostCenter($row->costCenter);
    }

    private function fromMapping(AssignmentRow $row, ItemMapping $mapping): void
    {
        $row->product = $mapping->getProduct();
        $row->skip = $mapping->isSkip();
        $row->factor = null !== $mapping->getFactor() ? (float) $mapping->getFactor() : null;
        $row->costCenter ??= $mapping->getCostCenter();
    }

    private static function currentFactor(InvoiceItem $item): ?float
    {
        $quantity = (float) $item->getQuantity();
        $converted = $item->getQuantityInProductUnit();

        return null !== $converted && 0.0 !== $quantity ? (float) $converted / $quantity : null;
    }

    /**
     * @return list<InvoiceItem>
     */
    private static function items(Invoice $invoice): array
    {
        $items = $invoice->getItems()->toArray();
        usort($items, static fn (InvoiceItem $a, InvoiceItem $b): int => [$a->getPosition(), $a->isBeforeCorrection() ? 0 : 1] <=> [$b->getPosition(), $b->isBeforeCorrection() ? 0 : 1]);

        return $items;
    }
}
