<?php

declare(strict_types=1);

namespace App\Controller;

use App\Dashboard\DashboardWidgets;
use App\Twig\FormatExtension;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\UX\Chartjs\Builder\ChartBuilderInterface;
use Symfony\UX\Chartjs\Model\Chart;

final class DashboardController extends AbstractController
{
    public function __construct(
        private readonly DashboardWidgets $widgets,
    ) {
    }

    #[Route('/', name: 'app_dashboard', methods: ['GET'])]
    public function index(Request $request): Response
    {
        return $this->render('dashboard/index.html.twig', [
            'catalog' => $this->widgets->catalog(),
            'editing' => $request->query->getBoolean('edycja'),
        ]);
    }

    #[Route('/pulpit/widzety/{key}/dodaj', name: 'app_dashboard_widget_add', methods: ['POST'])]
    public function add(string $key, Request $request): Response
    {
        $this->assertToken($request);
        $this->widgets->add($key);

        return $this->backToEditing();
    }

    #[Route('/pulpit/widzety/{key}/usun', name: 'app_dashboard_widget_remove', methods: ['POST'])]
    public function remove(string $key, Request $request): Response
    {
        $this->assertToken($request);
        $this->widgets->remove($key);

        return $this->backToEditing();
    }

    #[Route('/pulpit/widzety/{key}/przesun/{direction}', name: 'app_dashboard_widget_move', requirements: ['direction' => 'w-gore|w-dol'], methods: ['POST'])]
    public function move(string $key, string $direction, Request $request): Response
    {
        $this->assertToken($request);
        $this->widgets->move($key, 'w-gore' === $direction ? -1 : 1);

        return $this->backToEditing();
    }

    #[Route('/pulpit/widzety/przywroc', name: 'app_dashboard_widget_reset', methods: ['POST'])]
    public function reset(Request $request): Response
    {
        $this->assertToken($request);
        $this->widgets->reset();
        $this->addFlash('success', 'Przywrócono domyślny układ pulpitu.');

        return $this->redirectToRoute('app_dashboard', [], Response::HTTP_SEE_OTHER);
    }

    /**
     * @param array<string, float> $costs
     * @param array<string, float> $income
     */
    public static function chart(ChartBuilderInterface $charts, array $costs, array $income): Chart
    {
        $chart = $charts->createChart(Chart::TYPE_BAR);
        $chart->setData([
            'labels' => array_map(static fn (string $month): string => FormatExtension::month(new \DateTimeImmutable($month.'-01'), true), array_keys($costs)),
            'datasets' => [
                ['label' => 'Koszty', 'data' => array_values($costs), 'backgroundColor' => '#A9AFB5', 'borderRadius' => 4],
                ['label' => 'Przychody', 'data' => array_values($income), 'backgroundColor' => '#2E7D46', 'borderRadius' => 4],
            ],
        ]);
        $chart->setOptions([
            'maintainAspectRatio' => false,
            'plugins' => ['legend' => ['position' => 'bottom']],
            'scales' => ['y' => ['beginAtZero' => true]],
        ]);

        return $chart;
    }

    private function backToEditing(): Response
    {
        return $this->redirectToRoute('app_dashboard', ['edycja' => 1], Response::HTTP_SEE_OTHER);
    }

    private function assertToken(Request $request): void
    {
        if (!$this->isCsrfTokenValid('dashboard', $request->getPayload()->getString('_token'))) {
            throw $this->createAccessDeniedException();
        }
    }
}
