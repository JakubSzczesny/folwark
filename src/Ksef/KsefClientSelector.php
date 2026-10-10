<?php

declare(strict_types=1);

namespace App\Ksef;

use App\Ksef\Fake\FakeKsefClient;
use App\Ksef\Http\HttpKsefClient;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Wybiera implementację klienta według zmiennej KSEF_CLIENT (http | fake).
 */
final class KsefClientSelector
{
    public function __construct(
        private readonly HttpKsefClient $http,
        private readonly FakeKsefClient $fake,
        #[Autowire('%env(KSEF_CLIENT)%')]
        private readonly string $mode,
    ) {
    }

    public function select(): KsefClient
    {
        return 'fake' === $this->mode ? $this->fake : $this->http;
    }
}
