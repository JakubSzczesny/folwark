<?php

declare(strict_types=1);

namespace App\Controller\Warehouse;

use App\Repository\ProductCategoryRepository;
use App\Repository\StockRepository;
use App\Repository\WarehouseRepository;
use App\Security\Permission;
use App\Ui\ListFilters;
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

        $groups = [];
        foreach ($stocks->findForList($filters) as $stock) {
            $warehouse = $stock->getWarehouse();
            $groups[$warehouse->getId()] ??= ['warehouse' => $warehouse, 'stocks' => [], 'value' => 0.0];
            $groups[$warehouse->getId()]['stocks'][] = $stock;
            $groups[$warehouse->getId()]['value'] += (float) $stock->getValue();
        }

        return $this->render('warehouse/stock/index.html.twig', [
            'groups' => $groups,
            'filters' => $filters,
            'warehouses' => $warehouses->findBy([], ['name' => 'ASC']),
            'categories' => $categories->findBy([], ['name' => 'ASC']),
        ]);
    }
}
