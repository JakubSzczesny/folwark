<?php

declare(strict_types=1);

namespace App\Controller;

use App\Analytics\CenterReport;
use App\Analytics\IncomeAnalysis;
use App\Analytics\InvoiceStatistics;
use App\Analytics\PriceAnalysis;
use App\Analytics\UsageAnalysis;
use App\Contract\ContractRealization;
use App\Entity\MilkProduction;
use App\Enum\ContractStatus;
use App\Enum\CostCenterType;
use App\Enum\InvoiceKind;
use App\Repository\ContractorRepository;
use App\Repository\ContractRepository;
use App\Repository\CostCategoryRepository;
use App\Repository\CostCenterRepository;
use App\Repository\MilkProductionRepository;
use App\Security\Permission;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\UX\Chartjs\Builder\ChartBuilderInterface;
use Symfony\UX\Chartjs\Model\Chart;

/**
 * Analizy z faktur i ich pozycji (specyfikacja 14) – nie ze stanów magazynowych.
 */
#[Route('/analizy')]
#[IsGranted(Permission::ANALYTICS_VIEW)]
final class AnalyticsController extends AbstractController
{
    #[Route('', name: 'app_analytics_index', methods: ['GET'])]
    public function index(Request $request, InvoiceStatistics $statistics, ChartBuilderInterface $charts, CostCategoryRepository $categories, CostCenterRepository $centers, ContractorRepository $contractors): Response
    {
        $year = self::year($request);
        $filters = [
            'category' => (int) $request->query->getString('kategoria') ?: null,
            'contractor' => (int) $request->query->getString('kontrahent') ?: null,
            'costCenter' => (int) $request->query->getString('osrodek') ?: null,
        ];
        $from = new \DateTimeImmutable(\sprintf('%d-01-01', $year));
        $to = new \DateTimeImmutable(\sprintf('%d-12-31', $year));
        $incomeFilters = ['contractor' => $filters['contractor']];

        $costs = $statistics->monthly(InvoiceKind::Cost, $from, $to, $filters);
        $income = $statistics->monthly(InvoiceKind::Income, $from, $to, $incomeFilters);
        $previousCosts = array_values($statistics->monthly(InvoiceKind::Cost, $from->modify('-1 year'), $to->modify('-1 year'), $filters));
        $previousIncome = array_values($statistics->monthly(InvoiceKind::Income, $from->modify('-1 year'), $to->modify('-1 year'), $incomeFilters));

        $months = [];
        foreach (array_keys($costs) as $index => $month) {
            $months[] = [
                'month' => new \DateTimeImmutable($month.'-01'),
                'costs' => $costs[$month],
                'income' => $income[$month],
                'previousCosts' => $previousCosts[$index],
                'previousIncome' => $previousIncome[$index],
            ];
        }

        $chart = DashboardController::chart($charts, $costs, $income);
        $data = $chart->getData();
        $data['datasets'][] = ['label' => 'Koszty '.($year - 1), 'data' => $previousCosts, 'type' => 'line', 'borderColor' => '#5F666D', 'borderDash' => [4, 4], 'pointRadius' => 2, 'fill' => false];
        $data['datasets'][] = ['label' => 'Przychody '.($year - 1), 'data' => $previousIncome, 'type' => 'line', 'borderColor' => '#8FBF9F', 'borderDash' => [4, 4], 'pointRadius' => 2, 'fill' => false];
        $chart->setData($data);

        return $this->render('analytics/index.html.twig', [
            'tab' => 'costs',
            'year' => $year,
            'filters' => $filters,
            'months' => $months,
            'chart' => $chart,
            'categories' => $categories->findBy([], ['name' => 'ASC']),
            'centers' => $centers->findBy([], ['type' => 'ASC', 'name' => 'ASC']),
            'selected_contractor' => null !== $filters['contractor'] ? $contractors->find($filters['contractor']) : null,
        ]);
    }

    #[Route('/ceny', name: 'app_analytics_prices', methods: ['GET'])]
    public function prices(PriceAnalysis $prices): Response
    {
        $today = new \DateTimeImmutable('today');

        return $this->render('analytics/prices.html.twig', [
            'tab' => 'prices',
            'products' => $prices->products($today),
            'alerts' => $prices->recentAlerts($today),
            'threshold' => PriceAnalysis::THRESHOLD,
        ]);
    }

    #[Route('/osrodki', name: 'app_analytics_centers', methods: ['GET'])]
    public function centers(Request $request, CenterReport $report): Response
    {
        $year = self::year($request);

        return $this->render('analytics/centers.html.twig', ['tab' => 'centers', 'year' => $year] + $report->build($year));
    }

    #[Route('/osrodki/mleko', name: 'app_analytics_milk', methods: ['POST'])]
    public function milk(Request $request, CostCenterRepository $centers, MilkProductionRepository $productions, EntityManagerInterface $em): Response
    {
        if (!$this->isCsrfTokenValid('milk', $request->getPayload()->getString('_token'))) {
            throw $this->createAccessDeniedException();
        }

        $year = self::year($request);
        $center = $centers->find((int) $request->getPayload()->getString('center'));
        if (null === $center || CostCenterType::HerdGroup !== $center->getType()) {
            throw $this->createNotFoundException();
        }

        $existing = [];
        foreach ($productions->findBy(['costCenter' => $center]) as $production) {
            $existing[$production->getMonth()->format('Y-m')] = $production;
        }

        foreach ($request->getPayload()->all('liters') as $month => $value) {
            if (1 !== preg_match('/^\d{4}-\d{2}$/', (string) $month)) {
                continue;
            }
            $value = str_replace([' ', "\u{00A0}", ','], ['', '', '.'], trim((string) $value));
            $production = $existing[$month] ?? null;
            if ('' === $value || !is_numeric($value) || (float) $value < 0) {
                if (null !== $production) {
                    $em->remove($production);
                }
                continue;
            }
            if (null === $production) {
                $production = new MilkProduction($center, new \DateTimeImmutable($month.'-01'));
                $em->persist($production);
            }
            $production->setLiters(number_format((float) $value, 1, '.', ''));
        }
        $em->flush();
        $this->addFlash('success', \sprintf('Zapisano produkcję mleka – %s.', $center->getName()));

        return $this->redirectToRoute('app_analytics_centers', ['rok' => $year], Response::HTTP_SEE_OTHER);
    }

    #[Route('/zuzycie', name: 'app_analytics_usage', methods: ['GET'])]
    public function usage(UsageAnalysis $usage): Response
    {
        return $this->render('analytics/usage.html.twig', ['tab' => 'usage', 'products' => $usage->products(new \DateTimeImmutable('today'))]);
    }

    #[Route('/przychody', name: 'app_analytics_income', methods: ['GET'])]
    public function income(Request $request, IncomeAnalysis $income, ContractRepository $contracts, ContractRealization $realization, ChartBuilderInterface $charts): Response
    {
        $year = self::year($request);
        $from = new \DateTimeImmutable(\sprintf('%d-01-01', $year));
        $to = new \DateTimeImmutable(\sprintf('%d-12-31', $year));
        $customers = $income->byCustomer($from, $to);

        $chart = null;
        if (\count($customers) > 1) {
            $top = \array_slice($customers, 0, 8);
            $chart = $charts->createChart(Chart::TYPE_BAR);
            $chart->setData([
                'labels' => array_map(static fn (array $c): string => mb_strimwidth($c['contractor'], 0, 28, '…'), $top),
                'datasets' => [['label' => 'Przychody netto', 'data' => array_map(static fn (array $c): float => round($c['net'], 2), $top), 'backgroundColor' => '#2E7D46', 'borderRadius' => 4]],
            ]);
            $chart->setOptions(['indexAxis' => 'y', 'maintainAspectRatio' => false, 'plugins' => ['legend' => ['display' => false]]]);
        }

        $active = [];
        foreach ($contracts->findBy(['status' => ContractStatus::Active], ['validTo' => 'ASC']) as $contract) {
            if ($contract->isLease()) {
                continue;
            }
            $active[] = ['contract' => $contract, 'realization' => $realization->calculate($contract)];
        }

        return $this->render('analytics/income.html.twig', [
            'tab' => 'income',
            'year' => $year,
            'products' => $income->byProduct($from, $to),
            'customers' => $customers,
            'contracts' => $active,
            'chart' => $chart,
        ]);
    }

    private static function year(Request $request): int
    {
        $year = (int) ($request->query->getString('rok') ?: $request->getPayload()->getString('rok'));

        return $year >= 2000 && $year <= 2100 ? $year : (int) date('Y');
    }
}
