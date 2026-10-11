<?php

declare(strict_types=1);

namespace App\Product;

use App\Entity\Contractor;
use App\Entity\InvoiceItem;
use App\Entity\Product;
use App\Enum\InvoiceKind;
use App\Order\OrderPredictor;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Historia zakupów towaru z pozycji faktur: lista, ceny, dostawcy.
 */
final class ProductPurchases
{
    public function __construct(
        private readonly EntityManagerInterface $em,
    ) {
    }

    /**
     * @return list<array{date: \DateTimeImmutable, invoiceId: int, invoiceNumber: string, contractorId: ?int, contractor: string, quantity: ?float, net: float, unitPrice: ?float}>
     */
    public function rows(Product $product): array
    {
        /** @var list<InvoiceItem> $items */
        $items = $this->em->createQueryBuilder()
            ->select('it', 'i', 'c')
            ->from(InvoiceItem::class, 'it')
            ->join('it.invoice', 'i')
            ->leftJoin('i.contractor', 'c')
            ->where('it.product = :product')
            ->andWhere('i.kind = :kind')
            ->setParameter('product', $product)
            ->setParameter('kind', InvoiceKind::Cost)
            ->orderBy('i.issueDate', 'DESC')
            ->addOrderBy('it.position', 'ASC')
            ->getQuery()
            ->getResult();

        $rows = [];
        foreach ($items as $item) {
            $invoice = $item->getInvoice();
            $quantity = null !== $item->getQuantityInProductUnit() ? (float) $item->getQuantityInProductUnit() : null;
            $net = (float) $item->getNetValue();
            $rows[] = [
                'date' => $invoice->getIssueDate(),
                'invoiceId' => (int) $invoice->getId(),
                'invoiceNumber' => $invoice->getNumber(),
                'contractorId' => $invoice->getContractor()?->getId(),
                'contractor' => $invoice->getContractorLabel(),
                'quantity' => $quantity,
                'net' => $net,
                'unitPrice' => null !== $quantity && 0.0 !== $quantity ? $net / $quantity : null,
            ];
        }

        return $rows;
    }

    /**
     * Dostawcy towaru: ostatni zakup, ostatnia cena i mediana cen z 12 miesięcy.
     *
     * @param list<array{date: \DateTimeImmutable, contractorId: ?int, contractor: string, quantity: ?float, unitPrice: ?float}> $rows
     *
     * @return list<array{contractorId: ?int, contractor: string, lastDate: \DateTimeImmutable, lastPrice: ?float, medianPrice: ?float, purchases: int, quantity: float}>
     */
    public static function suppliers(array $rows, ?\DateTimeImmutable $today = null): array
    {
        $since = ($today ?? new \DateTimeImmutable('today'))->modify('-12 months');
        $groups = [];
        foreach ($rows as $row) {
            $key = $row['contractorId'] ?? $row['contractor'];
            $group = $groups[$key] ?? ['contractorId' => $row['contractorId'], 'contractor' => $row['contractor'], 'lastDate' => $row['date'], 'lastPrice' => $row['unitPrice'], 'prices' => [], 'purchases' => 0, 'quantity' => 0.0];
            if ($row['date'] > $group['lastDate']) {
                $group['lastDate'] = $row['date'];
                $group['lastPrice'] = $row['unitPrice'];
            }
            if (null !== $row['unitPrice'] && $row['date'] >= $since) {
                $group['prices'][] = $row['unitPrice'];
            }
            ++$group['purchases'];
            $group['quantity'] += max(0.0, $row['quantity'] ?? 0.0);
            $groups[$key] = $group;
        }

        $suppliers = [];
        foreach ($groups as $group) {
            $prices = $group['prices'];
            unset($group['prices']);
            $suppliers[] = $group + ['medianPrice' => OrderPredictor::median($prices)];
        }
        usort($suppliers, static fn (array $a, array $b): int => $b['lastDate'] <=> $a['lastDate']);

        return $suppliers;
    }

    /**
     * Towary kupowane u dostawcy – zakładka „Zakupy” na karcie kontrahenta.
     *
     * @return list<array{product: Product, lastDate: \DateTimeImmutable, lastQuantity: ?float, lastPrice: ?float, purchases: int}>
     */
    public function forContractor(Contractor $contractor): array
    {
        /** @var list<InvoiceItem> $items */
        $items = $this->em->createQueryBuilder()
            ->select('it', 'i', 'p', 'u')
            ->from(InvoiceItem::class, 'it')
            ->join('it.invoice', 'i')
            ->join('it.product', 'p')
            ->leftJoin('p.baseUnit', 'u')
            ->where('i.contractor = :contractor')
            ->andWhere('i.kind = :kind')
            ->andWhere('it.beforeCorrection = false')
            ->setParameter('contractor', $contractor)
            ->setParameter('kind', InvoiceKind::Cost)
            ->orderBy('i.issueDate', 'DESC')
            ->getQuery()
            ->getResult();

        $products = [];
        foreach ($items as $item) {
            $product = $item->getProduct();
            if (null === $product) {
                continue;
            }
            $id = (int) $product->getId();
            if (!isset($products[$id])) {
                $quantity = null !== $item->getQuantityInProductUnit() ? (float) $item->getQuantityInProductUnit() : null;
                $products[$id] = [
                    'product' => $product,
                    'lastDate' => $item->getInvoice()->getIssueDate(),
                    'lastQuantity' => $quantity,
                    'lastPrice' => null !== $quantity && 0.0 !== $quantity ? (float) $item->getNetValue() / $quantity : null,
                    'purchases' => 0,
                ];
            }
            ++$products[$id]['purchases'];
        }

        return array_values($products);
    }
}
