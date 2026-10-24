<?php

declare(strict_types=1);

namespace App\Ksef;

/**
 * Dozwolone wartości zmiennej KSEF_CLIENT.
 */
enum KsefClientMode: string
{
    case Http = 'http';
    case Fake = 'fake';
}
