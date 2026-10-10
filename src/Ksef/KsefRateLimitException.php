<?php

declare(strict_types=1);

namespace App\Ksef;

final class KsefRateLimitException extends KsefException
{
    public function __construct(
        public readonly int $retryAfterSeconds,
    ) {
        parent::__construct(\sprintf('Przekroczono limit zapytań KSeF. Kolejna próba możliwa za %d s.', $retryAfterSeconds));
    }
}
