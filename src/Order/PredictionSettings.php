<?php

declare(strict_types=1);

namespace App\Order;

final readonly class PredictionSettings
{
    /**
     * @param int $leadDays     wyprzedzenie przypomnienia (dni przed przewidywaną datą)
     * @param int $deliveryDays czas dostawy towaru lub dostawcy
     */
    public function __construct(
        public int $minOrders = 3,
        public int $mergeDays = 3,
        public float $regularThreshold = 0.25,
        public float $moderateThreshold = 0.5,
        public int $leadDays = 7,
        public int $deliveryDays = 0,
        public ?int $manualIntervalDays = null,
        public ?float $manualQuantity = null,
        public bool $seasonalDisabled = false,
    ) {
    }
}
