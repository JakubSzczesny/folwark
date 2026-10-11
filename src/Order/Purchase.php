<?php

declare(strict_types=1);

namespace App\Order;

/**
 * Jeden zakup z faktury: ilość towaru (w jednostce podstawowej) albo – na poziomie dostawcy – sama kwota.
 * Korekta nie jest nowym zamówieniem: zmienia ilość i kwotę zamówienia, którego dotyczy.
 */
final readonly class Purchase
{
    public function __construct(
        public \DateTimeImmutable $date,
        public ?float $quantity,
        public float $amount,
        public bool $correction = false,
        public ?int $invoiceId = null,
    ) {
    }
}
