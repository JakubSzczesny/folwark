<?php

declare(strict_types=1);

namespace App\Ksef;

use App\Ksef\Dto\MetadataPage;
use App\Ksef\Dto\MetadataQuery;

/**
 * Dostęp do KSeF w trybie odczytu. Implementacje: HttpKsefClient (API KSeF 2.0) i FakeKsefClient (pliki XML).
 */
interface KsefClient
{
    /**
     * @throws KsefException
     */
    public function queryMetadata(MetadataQuery $query): MetadataPage;

    /**
     * Zwraca pełny XML faktury.
     *
     * @throws KsefException
     */
    public function downloadInvoice(string $ksefNumber): string;

    /**
     * Sprawdza połączenie i uprawnienia; zwraca opis dla użytkownika.
     *
     * @throws KsefException
     */
    public function testConnection(): string;

    /**
     * Czy klient ma czym się uwierzytelnić (token) – bez tego synchronizacji w ogóle nie zaczynamy.
     */
    public function isConfigured(): bool;
}
