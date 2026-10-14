<?php

declare(strict_types=1);

namespace App\Analytics;

use App\Entity\CostCenter;
use App\Entity\CropSeason;
use App\Entity\Machine;
use App\Enum\CostCenterType;
use App\Repository\CostCenterRepository;
use App\Repository\CropSeasonRepository;
use App\Repository\MilkProductionRepository;

/**
 * Koszty per ośrodek w roku: pola i sezony (zł/ha), grupy stada (pasze/miesiąc, zł/l mleka),
 * maszyny (paliwo, części, serwis) i pozostałe.
 */
final class CenterReport
{
    public function __construct(
        private readonly CostCenterCosts $costs,
        private readonly CostCenterRepository $centers,
        private readonly CropSeasonRepository $seasons,
        private readonly MilkProductionRepository $milk,
    ) {
    }

    /**
     * @return array{
     *     seasons: list<array{season: CropSeason, total: float, perHa: ?float}>,
     *     herds: list<array{center: CostCenter, months: array<string, array{feed: float, total: float, liters: ?float}>, feed: float, total: float, liters: float}>,
     *     machines: list<array{center: CostCenter, machine: ?Machine, groups: array<string, float>, total: float}>,
     *     others: list<array{center: CostCenter, total: float}>
     * }
     */
    public function build(int $year): array
    {
        $from = new \DateTimeImmutable(\sprintf('%d-01-01', $year));
        $to = new \DateTimeImmutable(\sprintf('%d-12-31', $year));
        $yearEntries = $this->costs->entries($from, $to);
        $yearTotals = CostCenterCosts::totals($yearEntries);

        $seasons = [];
        $yearSeasons = $this->seasons->findBy(['year' => $year]);
        $seasonTotals = [] !== $yearSeasons ? CostCenterCosts::totals($this->costs->entries()) : [];
        foreach ($yearSeasons as $season) {
            $total = $seasonTotals[$season->getCostCenter()?->getId()] ?? 0.0;
            $area = $season->effectiveArea();
            $seasons[] = ['season' => $season, 'total' => $total, 'perHa' => $area > 0 ? $total / $area : null];
        }

        $milk = [];
        foreach ($this->milk->findBy([]) as $production) {
            if ((int) $production->getMonth()->format('Y') === $year) {
                $milk[$production->getCostCenter()->getId()][$production->getMonth()->format('Y-m')] = (float) $production->getLiters();
            }
        }

        $machines = $this->costs->machinesByCenter();
        $herds = [];
        $machineRows = [];
        $others = [];
        foreach ($this->centers->findBy([], ['type' => 'ASC', 'name' => 'ASC']) as $center) {
            $id = (int) $center->getId();
            $entries = array_values(array_filter($yearEntries, static fn (array $e): bool => $e['center'] === $id));

            if (CostCenterType::HerdGroup === $center->getType()) {
                if (!$center->isActive() && [] === $entries) {
                    continue;
                }
                $months = [];
                foreach (InvoiceStatistics::months($from, $to) as $month) {
                    $months[$month] = ['feed' => 0.0, 'total' => 0.0, 'liters' => $milk[$id][$month] ?? null];
                }
                foreach ($entries as $entry) {
                    $month = $entry['date']->format('Y-m');
                    if (!isset($months[$month])) {
                        continue;
                    }
                    $months[$month]['total'] += $entry['net'];
                    if (CostCenterCosts::isFeed($entry['category'])) {
                        $months[$month]['feed'] += $entry['net'];
                    }
                }
                $herds[] = [
                    'center' => $center,
                    'months' => $months,
                    'feed' => array_sum(array_column($months, 'feed')),
                    'total' => array_sum(array_column($months, 'total')),
                    'liters' => (float) array_sum(array_column($months, 'liters')),
                ];
            } elseif (CostCenterType::Machine === $center->getType() || isset($machines[$id])) {
                if ([] === $entries) {
                    continue;
                }
                $groups = [CostCenterCosts::FUEL => 0.0, CostCenterCosts::PARTS => 0.0, CostCenterCosts::SERVICE => 0.0];
                foreach ($entries as $entry) {
                    $groups[CostCenterCosts::machineGroup($entry['category'])] += $entry['net'];
                }
                $machineRows[] = ['center' => $center, 'machine' => $machines[$id] ?? null, 'groups' => $groups, 'total' => array_sum($groups)];
            } elseif (CostCenterType::FieldSeason !== $center->getType() && ($yearTotals[$id] ?? 0.0) !== 0.0) {
                $others[] = ['center' => $center, 'total' => $yearTotals[$id]];
            }
        }

        return ['seasons' => $seasons, 'herds' => $herds, 'machines' => $machineRows, 'others' => $others];
    }
}
