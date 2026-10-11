<?php

declare(strict_types=1);

namespace App\Tests\Unit\Order;

use App\Enum\OrderMode;
use App\Enum\OrderRegularity;
use App\Order\OrderPredictor;
use App\Order\PredictionSettings;
use App\Order\Purchase;
use PHPUnit\Framework\TestCase;

final class OrderPredictorTest extends TestCase
{
    private OrderPredictor $predictor;

    protected function setUp(): void
    {
        $this->predictor = new OrderPredictor();
    }

    public function testRegularPurchasesEvery21DaysPredictNextOrderAndReminder(): void
    {
        $purchases = [];
        foreach (['2026-07-01', '2026-07-22', '2026-08-12', '2026-09-02', '2026-09-23'] as $i => $date) {
            $purchases[] = new Purchase(new \DateTimeImmutable($date), 1000.0 + $i * 10, 6000.0);
        }

        $prediction = $this->predictor->predict($purchases, new PredictionSettings(leadDays: 7, deliveryDays: 2), new \DateTimeImmutable('2026-10-01'));

        self::assertSame(OrderMode::Continuous, $prediction->mode);
        self::assertSame(21, $prediction->intervalDays);
        self::assertSame(0, $prediction->spreadDays);
        self::assertSame(OrderRegularity::Regular, $prediction->regularity);
        self::assertSame('2026-10-14', $prediction->predictedDate?->format('Y-m-d'), 'Ostatni zakup + 21 dni');
        self::assertSame('2026-10-05', $prediction->reminderDate?->format('Y-m-d'), '7 dni wyprzedzenia + 2 dni dostawy wcześniej');
        self::assertSame(1020.0, $prediction->typicalQuantity, 'Mediana ilości z ostatnich 5 zamówień');
    }

    public function testPurchasesWithinThreeDaysAreOneOrderAndCorrectionsReduceIt(): void
    {
        $orders = OrderPredictor::merge([
            new Purchase(new \DateTimeImmutable('2026-03-01'), 500.0, 1000.0),
            new Purchase(new \DateTimeImmutable('2026-03-03'), 300.0, 600.0),
            new Purchase(new \DateTimeImmutable('2026-03-20'), 400.0, 800.0),
            new Purchase(new \DateTimeImmutable('2026-03-25'), -100.0, -200.0, correction: true),
        ], 3);

        self::assertCount(2, $orders);
        self::assertSame('2026-03-01', $orders[0]->date->format('Y-m-d'));
        self::assertSame(800.0, $orders[0]->quantity);
        self::assertSame(300.0, $orders[1]->quantity, 'Korekta pomniejsza zamówienie, którego dotyczy');
    }

    public function testFullyCorrectedOrderDisappears(): void
    {
        $orders = OrderPredictor::merge([
            new Purchase(new \DateTimeImmutable('2026-03-01'), 500.0, 1000.0),
            new Purchase(new \DateTimeImmutable('2026-03-10'), -500.0, -1000.0, correction: true),
        ], 3);

        self::assertSame([], $orders);
    }

    public function testTooFewOrdersMeansNotEnoughData(): void
    {
        $prediction = $this->predictor->predict([
            new Purchase(new \DateTimeImmutable('2026-08-01'), 10.0, 100.0),
            new Purchase(new \DateTimeImmutable('2026-09-01'), 10.0, 100.0),
        ], new PredictionSettings(), new \DateTimeImmutable('2026-10-01'));

        self::assertFalse($prediction->isSufficient());
        self::assertSame('2026-09-01', $prediction->lastOrder()?->date->format('Y-m-d'));
    }

    public function testMedianAndSpreadDecideRegularity(): void
    {
        $dates = ['2026-01-01', '2026-01-21', '2026-02-20', '2026-03-05', '2026-04-04'];
        $purchases = array_map(static fn (string $d): Purchase => new Purchase(new \DateTimeImmutable($d), 1.0, 10.0), $dates);

        $prediction = $this->predictor->predict($purchases, new PredictionSettings(), new \DateTimeImmutable('2026-04-10'));

        self::assertSame(25, $prediction->intervalDays, 'Odstępy 20, 30, 13, 30 → mediana 25');
        self::assertSame(5, $prediction->spreadDays, 'Odchylenia 5, 5, 12, 5 → mediana 5');
        self::assertSame(OrderRegularity::Regular, $prediction->regularity, '5 / 25 = 0,2 < 0,25');
    }

    public function testIrregularPurchases(): void
    {
        $dates = ['2026-01-01', '2026-01-05', '2026-03-01', '2026-03-10', '2026-06-01'];
        $purchases = array_map(static fn (string $d): Purchase => new Purchase(new \DateTimeImmutable($d), 1.0, 10.0), $dates);

        $prediction = $this->predictor->predict($purchases, new PredictionSettings(), new \DateTimeImmutable('2026-06-10'));

        self::assertSame(OrderRegularity::Irregular, $prediction->regularity);
    }

    public function testFertiliserBoughtEveryMarchIsSeasonal(): void
    {
        $prediction = $this->predictor->predict([
            new Purchase(new \DateTimeImmutable('2025-03-12'), 15.0, 35000.0),
            new Purchase(new \DateTimeImmutable('2026-03-09'), 16.0, 37000.0),
        ], new PredictionSettings(), new \DateTimeImmutable('2026-10-11'));

        self::assertSame(OrderMode::Seasonal, $prediction->mode);
        self::assertSame('2027-03-09', $prediction->predictedDate?->format('Y-m-d'), 'Pierwsze zamówienie ostatniego sezonu przesunięte o rok');
        self::assertSame(16.0, $prediction->typicalQuantity, 'Ilość z poprzedniego sezonu');
    }

    public function testSeasonalModeCanBeSwitchedOff(): void
    {
        $prediction = $this->predictor->predict([
            new Purchase(new \DateTimeImmutable('2025-03-12'), 15.0, 35000.0),
            new Purchase(new \DateTimeImmutable('2026-03-09'), 16.0, 37000.0),
        ], new PredictionSettings(seasonalDisabled: true), new \DateTimeImmutable('2026-10-11'));

        self::assertFalse($prediction->isSufficient());
    }

    public function testManualIntervalAndQuantityOverrideHistory(): void
    {
        $prediction = $this->predictor->predict([
            new Purchase(new \DateTimeImmutable('2026-09-20'), 3.0, 300.0),
        ], new PredictionSettings(manualIntervalDays: 30, manualQuantity: 4.0), new \DateTimeImmutable('2026-10-01'));

        self::assertSame(OrderMode::Manual, $prediction->mode);
        self::assertSame('2026-10-20', $prediction->predictedDate?->format('Y-m-d'));
        self::assertSame(4.0, $prediction->typicalQuantity);
    }

    public function testSupplierLevelWorksWithAmountsOnly(): void
    {
        $purchases = array_map(
            static fn (string $d): Purchase => new Purchase(new \DateTimeImmutable($d), null, 6000.0),
            ['2026-06-01', '2026-06-22', '2026-07-13', '2026-08-03'],
        );

        $prediction = $this->predictor->predict($purchases, new PredictionSettings(), new \DateTimeImmutable('2026-08-10'));

        self::assertSame('2026-08-24', $prediction->predictedDate?->format('Y-m-d'));
        self::assertNull($prediction->typicalQuantity);
        self::assertSame(6000.0, $prediction->typicalAmount);
    }

    public function testPriceChangeAgainstTwelveMonthMedian(): void
    {
        $purchases = [];
        foreach (['2026-05-01' => 6.0, '2026-06-01' => 6.0, '2026-07-01' => 6.0, '2026-08-01' => 6.6] as $date => $price) {
            $purchases[] = new Purchase(new \DateTimeImmutable($date), 100.0, 100.0 * $price);
        }

        $prediction = $this->predictor->predict($purchases, new PredictionSettings(), new \DateTimeImmutable('2026-08-10'));

        self::assertEqualsWithDelta(0.1, $prediction->priceChange(), 0.0001);
    }
}
