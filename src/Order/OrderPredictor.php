<?php

declare(strict_types=1);

namespace App\Order;

use App\Enum\OrderMode;
use App\Enum\OrderRegularity;

/**
 * Przewidywanie kolejnego zamówienia z powtarzalności zakupów (specyfikacja 9.2).
 * Czysta logika – dane wejściowe to lista zakupów, bez bazy danych.
 */
final class OrderPredictor
{
    private const int HISTORY_MONTHS = 24;
    private const int MAX_INTERVALS_FROM = 8;
    private const int TYPICAL_FROM = 5;
    private const float SEASON_SHARE = 0.7;
    private const int SEASON_MONTHS = 3;
    private const int SEASON_MIN_SPAN_DAYS = 330;
    private const int SEASON_WINDOW_DAYS = 14;
    private const int SEASON_REGULAR_SPREAD_DAYS = 21;

    /**
     * @param list<Purchase> $purchases
     */
    public function predict(array $purchases, PredictionSettings $settings, \DateTimeImmutable $today): Prediction
    {
        $today = $today->setTime(0, 0);
        $orders = self::merge($purchases, $settings->mergeDays);
        if ([] === $orders) {
            return new Prediction([]);
        }

        $last = $orders[array_key_last($orders)];
        $common = [
            'typicalQuantity' => $settings->manualQuantity ?? self::median(array_values(array_filter(array_map(static fn (MergedOrder $o): ?float => $o->quantity, \array_slice($orders, -self::TYPICAL_FROM)), static fn (?float $q): bool => null !== $q))),
            'typicalAmount' => self::median(array_map(static fn (MergedOrder $o): float => $o->amount, \array_slice($orders, -self::TYPICAL_FROM))),
            'dailyUsage' => self::dailyUsage($orders, $today),
            'medianUnitPrice' => self::median(array_values(array_filter(array_map(
                static fn (MergedOrder $o): ?float => $o->date >= $today->modify('-12 months') ? $o->unitPrice() : null,
                $orders,
            ), static fn (?float $p): bool => null !== $p))),
        ];

        if (null !== $settings->manualIntervalDays && $settings->manualIntervalDays > 0) {
            $predicted = $last->date->modify(\sprintf('+%d days', $settings->manualIntervalDays));

            return $this->result($orders, $common, $settings, OrderMode::Manual, null, $settings->manualIntervalDays, 0, $predicted, 0);
        }

        if (!$settings->seasonalDisabled && null !== $seasonal = $this->seasonal($orders, $settings, $common)) {
            return $seasonal;
        }

        $recent = array_filter($orders, static fn (MergedOrder $o): bool => $o->date >= $today->modify(\sprintf('-%d months', self::HISTORY_MONTHS)));
        if (\count($recent) < $settings->minOrders) {
            return new Prediction($orders, typicalQuantity: $common['typicalQuantity'], typicalAmount: $common['typicalAmount'], dailyUsage: $common['dailyUsage'], medianUnitPrice: $common['medianUnitPrice']);
        }

        $intervals = self::intervals(\array_slice($orders, -self::MAX_INTERVALS_FROM));
        $interval = (int) round((float) self::median($intervals));
        if ($interval <= 0) {
            return new Prediction($orders, typicalQuantity: $common['typicalQuantity'], typicalAmount: $common['typicalAmount'], dailyUsage: $common['dailyUsage'], medianUnitPrice: $common['medianUnitPrice']);
        }
        $spread = (int) round((float) self::median(array_map(static fn (int $i): float => abs($i - $interval), $intervals)));
        $ratio = $spread / $interval;
        $regularity = match (true) {
            $ratio < $settings->regularThreshold => OrderRegularity::Regular,
            $ratio <= $settings->moderateThreshold => OrderRegularity::Moderate,
            default => OrderRegularity::Irregular,
        };

        return $this->result($orders, $common, $settings, OrderMode::Continuous, $regularity, $interval, $spread, $last->date->modify(\sprintf('+%d days', $interval)), $spread);
    }

    /**
     * Zakupy w odstępie ≤ $mergeDays dni tworzą jedno zamówienie. Korekty zmieniają zamówienie,
     * którego dotyczą (ostatnie przed nimi); zamówienie skorygowane do zera znika.
     *
     * @param list<Purchase> $purchases
     *
     * @return list<MergedOrder>
     */
    public static function merge(array $purchases, int $mergeDays): array
    {
        usort($purchases, static fn (Purchase $a, Purchase $b): int => $a->date <=> $b->date);

        $orders = [];
        foreach ($purchases as $purchase) {
            $current = [] === $orders ? null : $orders[array_key_last($orders)];

            if ($purchase->correction) {
                $current?->add($purchase);
                continue;
            }
            if (null !== $current && self::days($current->lastPurchase, $purchase->date) <= $mergeDays) {
                $current->add($purchase);
                continue;
            }

            $order = new MergedOrder($purchase->date, $purchase->date, null, 0.0);
            $order->add($purchase);
            $orders[] = $order;
        }

        return array_values(array_filter($orders, static fn (MergedOrder $o): bool => null === $o->quantity ? $o->amount > 0 : $o->quantity > 0));
    }

    /**
     * Tryb sezonowy: historia z co najmniej dwóch lat i ≥ 70 % zamówień w tych samych ≤ 3 miesiącach.
     * Przewidywana data to pierwsze zamówienie ostatniego sezonu przesunięte o rok.
     *
     * @param list<MergedOrder>    $orders
     * @param array<string, mixed> $common
     */
    private function seasonal(array $orders, PredictionSettings $settings, array $common): ?Prediction
    {
        if ([] === $orders) {
            return null;
        }
        $first = $orders[0];
        $last = $orders[array_key_last($orders)];
        $years = array_unique(array_map(static fn (MergedOrder $o): string => $o->date->format('Y'), $orders));
        if (\count($orders) < 2 || \count($years) < 2 || self::days($first->date, $last->date) < self::SEASON_MIN_SPAN_DAYS) {
            return null;
        }

        $byMonth = array_count_values(array_map(static fn (MergedOrder $o): int => (int) $o->date->format('n'), $orders));
        arsort($byMonth);
        $months = \array_slice(array_keys($byMonth), 0, self::SEASON_MONTHS);
        $inSeason = array_values(array_filter($orders, static fn (MergedOrder $o): bool => \in_array((int) $o->date->format('n'), $months, true)));
        if (\count($inSeason) / \count($orders) < self::SEASON_SHARE) {
            return null;
        }

        $seasonStarts = [];
        $seasonQuantity = [];
        $seasonAmount = [];
        foreach ($inSeason as $order) {
            $year = (int) $order->date->format('Y');
            $seasonStarts[$year] ??= $order->date;
            if (null !== $order->quantity) {
                $seasonQuantity[$year] = ($seasonQuantity[$year] ?? 0.0) + $order->quantity;
            }
            $seasonAmount[$year] = ($seasonAmount[$year] ?? 0.0) + $order->amount;
        }
        if ([] === $seasonStarts) {
            return null;
        }
        $lastSeason = max(array_keys($seasonStarts));

        $daysOfYear = array_map(static fn (\DateTimeImmutable $d): int => (int) $d->format('z'), $seasonStarts);
        $regularity = max($daysOfYear) - min($daysOfYear) <= self::SEASON_REGULAR_SPREAD_DAYS ? OrderRegularity::Regular : OrderRegularity::Moderate;

        $common['typicalQuantity'] = $settings->manualQuantity ?? $seasonQuantity[$lastSeason] ?? $common['typicalQuantity'];
        $common['typicalAmount'] = $seasonAmount[$lastSeason];

        return $this->result($orders, $common, $settings, OrderMode::Seasonal, $regularity, 365, self::SEASON_WINDOW_DAYS, $seasonStarts[$lastSeason]->modify('+1 year'), self::SEASON_WINDOW_DAYS);
    }

    /**
     * @param list<MergedOrder>    $orders
     * @param array<string, mixed> $common
     */
    private function result(array $orders, array $common, PredictionSettings $settings, OrderMode $mode, ?OrderRegularity $regularity, int $interval, int $spread, \DateTimeImmutable $predicted, int $window): Prediction
    {
        return new Prediction(
            $orders,
            $mode,
            $regularity,
            $interval,
            $spread,
            $predicted,
            $predicted->modify(\sprintf('-%d days', $window)),
            $predicted->modify(\sprintf('+%d days', $window)),
            $predicted->modify(\sprintf('-%d days', $settings->deliveryDays + $settings->leadDays)),
            $common['typicalQuantity'],
            $common['typicalAmount'],
            $common['dailyUsage'],
            $common['medianUnitPrice'],
        );
    }

    /**
     * @param list<MergedOrder> $orders
     *
     * @return list<int>
     */
    private static function intervals(array $orders): array
    {
        $intervals = [];
        for ($i = 1, $n = \count($orders); $i < $n; ++$i) {
            $intervals[] = self::days($orders[$i - 1]->date, $orders[$i]->date);
        }

        return $intervals;
    }

    /**
     * Średnie dzienne zużycie z zakupów ostatnich 12 miesięcy.
     *
     * @param list<MergedOrder> $orders
     */
    private static function dailyUsage(array $orders, \DateTimeImmutable $today): ?float
    {
        $since = $today->modify('-365 days');
        $sum = null;
        foreach ($orders as $order) {
            if ($order->date > $since && null !== $order->quantity) {
                $sum = ($sum ?? 0.0) + $order->quantity;
            }
        }

        return null === $sum ? null : $sum / 365;
    }

    /**
     * @param list<int|float> $values
     */
    public static function median(array $values): ?float
    {
        if ([] === $values) {
            return null;
        }

        sort($values);
        $middle = intdiv(\count($values), 2);

        return 0 === \count($values) % 2 ? ($values[$middle - 1] + $values[$middle]) / 2 : (float) $values[$middle];
    }

    private static function days(\DateTimeImmutable $from, \DateTimeImmutable $to): int
    {
        return (int) $from->setTime(0, 0)->diff($to->setTime(0, 0))->format('%r%a');
    }
}
