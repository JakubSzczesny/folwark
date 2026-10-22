<?php

declare(strict_types=1);

namespace App\Controller\Warehouse;

use App\Entity\Warehouse;
use App\Repository\ProductCategoryRepository;
use App\Repository\StockRepository;
use App\Repository\WarehouseRepository;
use App\Security\Permission;
use App\Ui\ListFilters;
use App\Ui\Sort;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Stany orientacyjne – suma zatwierdzonych dokumentów, bez ewidencji zużycia.
 */
#[Route('/magazyn/stany')]
#[IsGranted(Permission::DOCUMENT_VIEW)]
final class StockController extends AbstractController
{
    #[Route('', name: 'app_stock_index', methods: ['GET'])]
    public function index(Request $request, ListFilters $listFilters, StockRepository $stocks, WarehouseRepository $warehouses, ProductCategoryRepository $categories): Response
    {
        if ($redirect = $listFilters->restore($request, 'stock')) {
            return $redirect;
        }

        $filters = [
            'warehouse' => (int) $request->query->getString('magazyn') ?: null,
            'category' => (int) $request->query->getString('kategoria') ?: null,
            'q' => trim($request->query->getString('q')),
            'zero' => $request->query->getBoolean('zerowe'),
        ];

        $fields = $this->isGranted(Permission::AMOUNTS_VIEW) ? StockRepository::SORT : array_diff_key(StockRepository::SORT, ['cena' => true, 'wartosc' => true]);
        $sort = Sort::fromRequest($request, $fields, 'towar', 'asc', ['stan', 'cena', 'wartosc', 'ruch']);

        // Grupy w kolejności magazynów, sortowanie obowiązuje wewnątrz grupy.
        $all = $warehouses->findBy([], ['name' => 'ASC']);
        $groups = array_fill_keys(array_map(static fn (Warehouse $w): int => (int) $w->getId(), $all), null);
        foreach ($stocks->findForList($filters, $sort) as $stock) {
            $warehouse = $stock->getWarehouse();
            $groups[$warehouse->getId()] ??= ['warehouse' => $warehouse, 'stocks' => [], 'value' => 0.0];
            $groups[$warehouse->getId()]['stocks'][] = $stock;
            $groups[$warehouse->getId()]['value'] += (float) $stock->getValue();
        }

        return $this->render('warehouse/stock/index.html.twig', [
            'groups' => array_filter($groups),
            'filters' => $filters,
            'sort' => $sort,
            'warehouses' => $all,
            'categories' => $categories->findBy([], ['name' => 'ASC']),
        ]);
    }
}
