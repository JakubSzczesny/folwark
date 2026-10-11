<?php

declare(strict_types=1);

namespace App\Controller\Product;

use App\Audit\AuditLogger;
use App\Entity\InvoiceItem;
use App\Entity\Product;
use App\Form\OrderSettingsType;
use App\Form\ProductType;
use App\Order\OrderPlanner;
use App\Product\ProductLinks;
use App\Product\ProductPurchases;
use App\Product\UnitParser;
use App\Repository\InvoiceItemRepository;
use App\Repository\OrderCycleRepository;
use App\Repository\ProductCategoryRepository;
use App\Repository\ProductRepository;
use App\Security\Permission;
use App\Twig\FormatExtension;
use App\Ui\ListFilters;
use App\Ui\Pager;
use App\Ui\Sort;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\UX\Chartjs\Builder\ChartBuilderInterface;
use Symfony\UX\Chartjs\Model\Chart;
use Symfony\UX\Turbo\TurboBundle;

#[Route('/towary')]
#[IsGranted(Permission::PRODUCT_VIEW)]
final class ProductController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly AuditLogger $audit,
        private readonly OrderCycleRepository $cycles,
    ) {
    }

    #[Route('', name: 'app_product_index', methods: ['GET'])]
    public function index(Request $request, ListFilters $listFilters, ProductRepository $products, ProductCategoryRepository $categories): Response
    {
        if ($redirect = $listFilters->restore($request, 'products')) {
            return $redirect;
        }

        $filters = [
            'q' => trim($request->query->getString('szukaj')) ?: null,
            'category' => (int) $request->query->getString('kategoria') ?: null,
            'inactive' => $request->query->getBoolean('nieaktywne'),
        ];
        $sort = Sort::fromRequest($request, ProductRepository::SORT, 'nazwa', 'asc');
        $pager = Pager::fromQuery($products->createListQuery($filters, $sort), $request->query->getInt('strona', 1));

        $cycles = [];
        foreach ($pager->items as $product) {
            $cycles[$product->getId()] = $this->cycles->findOpenFor($product);
        }

        return $this->render('product/index.html.twig', [
            'pager' => $pager,
            'filters' => $filters,
            'sort' => $sort,
            'categories' => $categories->findTreeOrdered(),
            'cycles' => $cycles,
        ]);
    }

    #[Route('/nowy', name: 'app_product_new', methods: ['GET', 'POST'])]
    #[IsGranted(Permission::PRODUCT_EDIT)]
    public function new(Request $request): Response
    {
        $product = new Product();
        $product->setName($request->query->getString('nazwa'));

        return $this->handleForm($product, $request);
    }

    #[Route('/{id}/edycja', name: 'app_product_edit', requirements: ['id' => '\d+'], methods: ['GET', 'POST'])]
    #[IsGranted(Permission::PRODUCT_EDIT)]
    public function edit(Product $product, Request $request): Response
    {
        return $this->handleForm($product, $request);
    }

    #[Route('/{id}', name: 'app_product_show', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function show(Product $product, ProductPurchases $purchases, ChartBuilderInterface $charts): Response
    {
        $rows = $purchases->rows($product);
        $amounts = $this->isGranted(Permission::AMOUNTS_VIEW);

        return $this->renderTab($product, 'purchases', [
            'rows' => $rows,
            'timeline' => self::timeline($rows),
            'price_chart' => $amounts ? $this->priceChart($rows, $charts) : null,
        ]);
    }

    #[Route('/{id}/dostawcy', name: 'app_product_suppliers', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function suppliers(Product $product, ProductPurchases $purchases): Response
    {
        return $this->renderTab($product, 'suppliers', ['suppliers' => ProductPurchases::suppliers($purchases->rows($product))]);
    }

    #[Route('/{id}/linki', name: 'app_product_links', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function links(Product $product, Request $request, ProductLinks $links): Response
    {
        $showAll = $request->query->getBoolean('wszystkie');

        return $this->renderTab($product, 'links', [
            'tree' => $links->tree($product, (int) $request->query->getString('kategoria') ?: null, $showAll),
            'sections' => $links->sections($product),
            'show_all' => $showAll,
        ]);
    }

    #[Route('/{id}/zamowienie', name: 'app_product_order', requirements: ['id' => '\d+'], methods: ['GET', 'POST'])]
    #[IsGranted(Permission::ORDER_VIEW)]
    public function order(Product $product, Request $request, OrderPlanner $planner): Response
    {
        $settings = $planner->settingsFor($product);
        $form = $this->createForm(OrderSettingsType::class, $settings);
        $form->handleRequest($request);

        if ($form->isSubmitted()) {
            $this->denyAccessUnlessGranted(Permission::ORDER_MANAGE);
            if ($form->isValid()) {
                $this->em->flush();
                $planner->recalculate();
                $this->addFlash('success', 'Zapisano ustawienia zamówienia.');

                return $this->redirectToRoute('app_product_order', ['id' => $product->getId()], Response::HTTP_SEE_OTHER);
            }
        }

        return $this->renderTab($product, 'order', [
            'form' => $form,
            'prediction' => $planner->prediction($product),
            'history' => $this->cycles->findHistory($product),
        ], $form->isSubmitted() ? 422 : 200);
    }

    /**
     * Szybkie dodanie towaru z okna dialogowego (np. przy przypisywaniu pozycji faktury).
     */
    #[Route('/szybki', name: 'app_product_quick', methods: ['GET', 'POST'])]
    #[IsGranted(Permission::PRODUCT_EDIT)]
    public function quick(Request $request, InvoiceItemRepository $items, UnitParser $units): Response
    {
        $product = new Product();
        $product->setName($request->query->getString('nazwa'));
        $form = $this->createForm(ProductType::class, $product, ['compact' => true, 'action' => $request->getRequestUri()]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->em->persist($product);
            $this->em->flush();
            $this->audit->log('Product', $product->getId(), 'create', ['name' => $product->getName()]);
            $this->em->flush();

            if (TurboBundle::STREAM_FORMAT === $request->getPreferredFormat()) {
                $request->setRequestFormat(TurboBundle::STREAM_FORMAT);
                $item = $items->find((int) $request->query->getString('wiersz'));
                $factor = $item instanceof InvoiceItem && $item->getInvoice()->getFarm()?->getId() === $product->getFarm()?->getId()
                    ? $units->factor($item->getName(), $item->getUnit(), $product)
                    : null;

                return $this->render('product/_created.stream.html.twig', ['product' => $product, 'row' => $request->query->getString('wiersz'), 'factor' => $factor]);
            }

            return $this->redirectToRoute('app_product_show', ['id' => $product->getId()], Response::HTTP_SEE_OTHER);
        }

        return $this->render('product/quick.html.twig', ['form' => $form], new Response(null, $form->isSubmitted() ? 422 : 200));
    }

    /**
     * @param array<string, mixed> $context
     */
    private function renderTab(Product $product, string $tab, array $context = [], int $status = 200): Response
    {
        return $this->render('product/show.html.twig', $context + [
            'product' => $product,
            'tab' => $tab,
            'cycle' => $this->cycles->findOpenFor($product),
        ], new Response(null, $status));
    }

    private function handleForm(Product $product, Request $request): Response
    {
        $isNew = null === $product->getId();
        $form = $this->createForm(ProductType::class, $product, ['with_amounts' => $this->isGranted(Permission::AMOUNTS_VIEW)]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->em->persist($product);
            $this->em->flush();
            $this->audit->log('Product', $product->getId(), $isNew ? 'create' : 'update', ['name' => $product->getName()]);
            $this->em->flush();
            $this->addFlash('success', $isNew ? 'Dodano towar.' : 'Zapisano towar.');

            return $this->redirectToRoute('app_product_show', ['id' => $product->getId()], Response::HTTP_SEE_OTHER);
        }

        return $this->render('product/form.html.twig', ['form' => $form, 'product' => $product], new Response(null, $form->isSubmitted() ? 422 : 200));
    }

    /**
     * Oś czasu zakupów: punkty w procentach szerokości, wielkość wg ilości.
     *
     * @param list<array{date: \DateTimeImmutable, quantity: ?float, invoiceNumber: string}> $rows
     *
     * @return array{points: list<array{x: float, r: float, date: \DateTimeImmutable, quantity: ?float, label: string}>, from: ?\DateTimeImmutable, to: ?\DateTimeImmutable}
     */
    private static function timeline(array $rows): array
    {
        $rows = array_values(array_filter($rows, static fn (array $r): bool => null === $r['quantity'] || $r['quantity'] > 0));
        if ([] === $rows) {
            return ['points' => [], 'from' => null, 'to' => null];
        }

        $dates = array_map(static fn (array $r): int => $r['date']->getTimestamp(), $rows);
        $from = min($dates);
        $span = max(1, max($dates) - $from);
        $maxQuantity = max(array_map(static fn (array $r): float => $r['quantity'] ?? 0.0, $rows)) ?: 1.0;

        $points = [];
        foreach ($rows as $row) {
            $points[] = [
                'x' => round(($row['date']->getTimestamp() - $from) / $span * 100, 2),
                'r' => round(4 + 10 * sqrt(($row['quantity'] ?? 0.0) / $maxQuantity), 1),
                'date' => $row['date'],
                'quantity' => $row['quantity'],
                'label' => $row['invoiceNumber'],
            ];
        }

        return ['points' => $points, 'from' => (new \DateTimeImmutable())->setTimestamp($from), 'to' => (new \DateTimeImmutable())->setTimestamp(max($dates))];
    }

    /**
     * @param list<array{date: \DateTimeImmutable, unitPrice: ?float}> $rows
     */
    private function priceChart(array $rows, ChartBuilderInterface $charts): ?Chart
    {
        $points = array_reverse(array_values(array_filter($rows, static fn (array $r): bool => null !== $r['unitPrice'] && $r['unitPrice'] > 0)));
        if (\count($points) < 2) {
            return null;
        }

        $chart = $charts->createChart(Chart::TYPE_LINE);
        $chart->setData([
            'labels' => array_map(static fn (array $r): string => FormatExtension::date($r['date']), $points),
            'datasets' => [[
                'label' => 'Cena jednostkowa netto',
                'data' => array_map(static fn (array $r): float => round((float) $r['unitPrice'], 4), $points),
                'borderColor' => '#2E7D46',
                'backgroundColor' => 'rgba(46, 125, 70, 0.1)',
                'pointRadius' => 3,
                'tension' => 0.2,
                'fill' => true,
            ]],
        ]);
        $chart->setOptions([
            'maintainAspectRatio' => false,
            'plugins' => ['legend' => ['display' => false]],
            'scales' => ['y' => ['beginAtZero' => false]],
        ]);

        return $chart;
    }
}
