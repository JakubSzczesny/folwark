<?php

declare(strict_types=1);

namespace App\Ksef;

use App\Ksef\Fake\FakeKsefClient;
use App\Ksef\Http\HttpKsefClient;
use App\Ksef\Http\KsefEnvironment;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Wybiera implementację klienta według zmiennej KSEF_CLIENT (http | fake) i pilnuje, żeby pasowała do gospodarstwa.
 */
final class KsefClientSelector
{
    public function __construct(
        private readonly HttpKsefClient $http,
        private readonly FakeKsefClient $fake,
        private readonly KsefIdentity $identity,
        #[Autowire('%env(KSEF_CLIENT)%')]
        private readonly string $mode,
    ) {
    }

    public function select(): KsefClient
    {
        return KsefClientMode::Fake === $this->mode() ? $this->fake : $this->http;
    }

    /**
     * Null, gdy KSEF_CLIENT ma niedozwoloną wartość.
     */
    public function mode(): ?KsefClientMode
    {
        return KsefClientMode::tryFrom($this->mode);
    }

    /**
     * Dlaczego bieżące gospodarstwo nie może się synchronizować tym klientem (null – może).
     */
    public function configurationError(): ?string
    {
        $mode = $this->mode();
        if (null === $mode) {
            return \sprintf('Nieprawidłowa wartość KSEF_CLIENT „%s”. Dozwolone: http (prawdziwy KSeF) albo fake (symulator).', $this->mode);
        }

        // Symulator nie zna prawdziwych faktur – bez tej blokady „synchronizował” produkcyjne gospodarstwo bez błędu i przesuwał kursor.
        if (KsefClientMode::Fake === $mode && KsefEnvironment::Production === $this->identity->environment()) {
            return 'Gospodarstwo ma ustawione produkcyjne środowisko KSeF, a aplikacja działa na symulatorze (KSEF_CLIENT=fake), więc nie pobrałaby prawdziwych faktur. Ustaw KSEF_CLIENT=http albo wybierz środowisko testowe.';
        }

        return null;
    }

    /**
     * Skąd pochodzą faktury: klient, środowisko i NIP. Postęp pobierania zapisany dla innego źródła nie dotyczy bieżących faktur.
     */
    public function source(): string
    {
        return implode('|', [$this->mode, $this->identity->environment()->value, $this->identity->nip() ?? '']);
    }
}
