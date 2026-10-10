<?php

declare(strict_types=1);

namespace App\WhiteList;

/**
 * Wykaz podatników VAT (Biała lista MF). Implementacje: MfWhiteListClient i FakeWhiteListClient.
 */
interface WhiteListClient
{
    /**
     * @throws WhiteListException
     */
    public function findByNip(string $nip, \DateTimeImmutable $date): ?WhiteListSubject;
}
