<?php

declare(strict_types=1);

namespace App\Message;

/**
 * Synchronizacja metadanych faktur z KSeF. Harmonogram wysyła ją co 5 minut, a obsługa sama
 * sprawdza, czy minął interwał z ustawień; ręczne „Synchronizuj teraz” pomija ten warunek.
 */
final class SyncKsefMetadata implements AsyncMessage
{
    public function __construct(
        public readonly bool $manual = false,
        public readonly ?int $userId = null,
    ) {
    }
}
