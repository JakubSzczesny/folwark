<?php

declare(strict_types=1);

namespace App\Controller;

use App\Analytics\InvoiceStatistics;
use App\Enum\InvoiceKind;
use App\Security\Permission;
use App\Twig\FormatExtension;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\UX\Chartjs\Builder\ChartBuilderInterface;
use Symfony\UX\Chartjs\Model\Chart;

final class DashboardController extends AbstractController
{
    #[Route('/', name: 'app_dashboard', methods: ['GET'])]
    public function index(InvoiceStatistics $statistics, ChartBuilderInterface $charts): Response
    {
        $chart = null;
        if ($this->isGranted(Permission::AMOUNTS_VIEW)) {
            $costs = $statistics->lastTwelveMonths(InvoiceKind::Cost);
            $income = $statistics->lastTwelveMonths(InvoiceKind::Income);
            $chart = self::chart($charts, $costs, $income);
        }

        return $this->render('dashboard/index.html.twig', ['chart' => $chart]);
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
}
