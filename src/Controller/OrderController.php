<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\OrderCycle;
use App\Entity\User;
use App\Enum\OrderMode;
use App\Enum\OrderRegularity;
use App\Enum\OrderStatus;
use App\Form\OrderSettingsType;
use App\Order\OrderPlanner;
use App\Product\ProductPurchases;
use App\Repository\ContractorRepository;
use App\Repository\InvoiceRepository;
use App\Repository\OrderCycleRepository;
use App\Repository\ProductCategoryRepository;
use App\Security\Permission;
use App\Ui\ListFilters;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Ekran „Zamówienia” – przewidywane zakupy z powtarzalności faktur (specyfikacja 9.4).
 */
#[Route('/zamowienia')]
#[IsGranted(Permission::ORDER_VIEW)]
final class OrderController extends AbstractController
{
    use ModalResponses;

    public const array GROUPS = [
        'overdue' => 'Po terminie',
        'week' => 'W tym tygodniu',
        'month' => 'Następne 30 dni',
        'later' => 'Później',
        'insufficient' => 'Za mało danych',
    ];

    public function __construct(
        private readonly OrderCycleRepository $cycles,
        private readonly OrderPlanner $planner,
    ) {
    }

    #[Route('', name: 'app_order_index', methods: ['GET'])]
    public function index(Request $request, ListFilters $listFilters, ProductCategoryRepository $categories, ContractorRepository $contractors): Response
    {
        if (!$request->query->has('cykl') && $redirect = $listFilters->restore($request, 'orders')) {
            return $redirect;
        }

        $filters = [
            'category' => (int) $request->query->getString('kategoria') ?: null,
            'contractor' => (int) $request->query->getString('dostawca') ?: null,
            'regularity' => OrderRegularity::tryFrom($request->query->getString('regularnosc'))?->value,
            'mode' => OrderMode::tryFrom($request->query->getString('tryb'))?->value,
            'hidden' => $request->query->getBoolean('ukryte'),
        ];

        $today = new \DateTimeImmutable('today');
        $groups = array_fill_keys(array_keys(self::GROUPS), []);
        foreach ($this->cycles->findForList($filters) as $cycle) {
            $groups[self::group($cycle, $today)][] = $cycle;
        }

        return $this->render('order/index.html.twig', [
            'groups' => $groups,
            'labels' => self::GROUPS,
            'filters' => $filters,
            'categories' => $categories->findTreeOrdered(),
            'selected_contractor' => null !== $filters['contractor'] ? $contractors->find($filters['contractor']) : null,
            'open_cycle' => (int) $request->query->getString('cykl') ?: null,
        ]);
    }

    #[Route('/{id}/podglad', name: 'app_order_preview', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function preview(OrderCycle $cycle, ProductPurchases $purchases, InvoiceRepository $invoices): Response
    {
        $product = $cycle->getProduct();
        $rows = null !== $product ? $purchases->rows($product) : [];

        return $this->render('order/_preview.html.twig', [
            'cycle' => $cycle,
            'prediction' => $this->planner->prediction($product ?? $cycle->getContractor() ?? throw $this->createNotFoundException()),
            'rows' => \array_slice($rows, 0, 15),
            'suppliers' => ProductPurchases::suppliers($rows),
            'invoices' => null === $product && null !== $cycle->getContractor() ? $invoices->findBy(['contractor' => $cycle->getContractor()], ['issueDate' => 'DESC'], 15) : [],
        ]);
    }

    #[Route('/{id}/zamowione', name: 'app_order_mark', requirements: ['id' => '\d+'], methods: ['POST'])]
    #[IsGranted(Permission::ORDER_MARK)]
    public function markOrdered(OrderCycle $cycle, Request $request, #[CurrentUser] User $user): Response
    {
        $this->assertToken($request);
        $this->planner->mark($cycle, OrderStatus::Ordered, $user);
        $this->addFlash('success', \sprintf('„%s” oznaczone jako zamówione – przypomnienia wstrzymane do nadejścia faktury.', $cycle->getLabel()));

        return $this->back($request);
    }

    #[Route('/{id}/pomin', name: 'app_order_skip', requirements: ['id' => '\d+'], methods: ['POST'])]
    #[IsGranted(Permission::ORDER_MANAGE)]
    public function skip(OrderCycle $cycle, Request $request, #[CurrentUser] User $user): Response
    {
        $this->assertToken($request);
        $this->planner->mark($cycle, OrderStatus::Skipped, $user);
        $this->addFlash('success', \sprintf('Pominięto ten cykl „%s”. Następny wyliczy się po kolejnej fakturze.', $cycle->getLabel()));

        return $this->back($request);
    }

    #[Route('/{id}/cofnij', name: 'app_order_unmark', requirements: ['id' => '\d+'], methods: ['POST'])]
    #[IsGranted(Permission::ORDER_MARK)]
    public function unmark(OrderCycle $cycle, Request $request): Response
    {
        $this->assertToken($request);
        $this->planner->unmark($cycle);

        return $this->back($request);
    }

    #[Route('/{id}/ustawienia', name: 'app_order_settings', requirements: ['id' => '\d+'], methods: ['GET', 'POST'])]
    #[IsGranted(Permission::ORDER_MANAGE)]
    public function settings(OrderCycle $cycle, Request $request, EntityManagerInterface $em): Response
    {
        $settings = $this->planner->settingsFor($cycle->getProduct() ?? $cycle->getContractor() ?? throw $this->createNotFoundException());
        $form = $this->createForm(OrderSettingsType::class, $settings, ['action' => $request->getRequestUri()]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em->flush();
            $this->planner->recalculate();
            $this->addFlash('success', 'Zapisano ustawienia.');

            return $this->visit($request, $this->generateUrl('app_order_index'));
        }

        return $this->render('order/settings.html.twig', ['form' => $form, 'cycle' => $cycle], new Response(null, $form->isSubmitted() ? 422 : 200));
    }

    #[Route('/przelicz', name: 'app_order_recalculate', methods: ['POST'])]
    #[IsGranted(Permission::ORDER_MANAGE)]
    public function recalculate(Request $request): Response
    {
        $this->assertToken($request);
        $this->planner->recalculate();
        $this->addFlash('success', 'Przeliczono przewidywania zamówień.');

        return $this->redirectToRoute('app_order_index', [], Response::HTTP_SEE_OTHER);
    }

    public static function group(OrderCycle $cycle, \DateTimeImmutable $today): string
    {
        $date = $cycle->getPredictedDate();

        return match (true) {
            null === $date => 'insufficient',
            OrderStatus::Overdue === $cycle->getStatus() => 'overdue',
            $date <= $today->modify('sunday this week') => 'week',
            $date <= $today->modify('+30 days') => 'month',
            default => 'later',
        };
    }

    private function back(Request $request): Response
    {
        $referer = (string) $request->headers->get('referer');
        $path = (string) parse_url($referer, \PHP_URL_PATH);
        $query = (string) parse_url($referer, \PHP_URL_QUERY);
        if (parse_url($referer, \PHP_URL_HOST) !== $request->getHost() || !preg_match('~^/(zamowienia|towary)(/|$)~', $path)) {
            return $this->redirectToRoute('app_order_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->redirect($path.('' !== $query ? '?'.$query : ''), Response::HTTP_SEE_OTHER);
    }

    private function assertToken(Request $request): void
    {
        if (!$this->isCsrfTokenValid('order', $request->getPayload()->getString('_token'))) {
            throw $this->createAccessDeniedException();
        }
    }
}
