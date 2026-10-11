<?php

declare(strict_types=1);

namespace App\Message;

/**
 * Przeliczenie cykli zamówień – jednego gospodarstwa albo (z harmonogramu) wszystkich.
 */
final class RecalculateOrders implements AsyncMessage
{
    public function __construct(
        public readonly ?int $farmId = null,
    ) {
    }
}
