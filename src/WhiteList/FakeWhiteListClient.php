<?php

declare(strict_types=1);

namespace App\WhiteList;

/**
 * Symulator Białej listy do pracy lokalnej i testów. Zwraca podmioty dodane przez add();
 * dla pozostałych NIP-ów generuje przewidywalne dane.
 */
class FakeWhiteListClient implements WhiteListClient
{
    /** @var array<string, WhiteListSubject|null> */
    private array $subjects = [];

    public function add(string $nip, ?WhiteListSubject $subject): void
    {
        $this->subjects[$nip] = $subject;
    }

    public function findByNip(string $nip, \DateTimeImmutable $date): ?WhiteListSubject
    {
        if (\array_key_exists($nip, $this->subjects)) {
            return $this->subjects[$nip];
        }

        $number = (int) substr($nip, -3);

        return new WhiteListSubject(
            nip: $nip,
            name: 'PODMIOT TESTOWY '.$nip.' SP. Z O.O.',
            statusVat: 'Czynny',
            address: \sprintf('UL. TESTOWA %d, %02d-%03d WARSZAWA', $number % 100 + 1, $number % 100, $number),
            accountNumbers: [\sprintf('%026d', (int) $nip)],
        );
    }
}
