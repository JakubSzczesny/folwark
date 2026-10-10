<?php

declare(strict_types=1);

namespace App\Message;

/**
 * Synchronizacja metadanych faktur z KSeF. Harmonogram wysyła ją co 5 minut bez gospodarstwa –
 * wtedy obsługa przechodzi po wszystkich gospodarstwach, którym minął interwał z ustawień.
 * Ręczne „Synchronizuj teraz” wskazuje gospodarstwo i pomija ten warunek.
 */
final class SyncKsefMetadata implements AsyncMessage
{
    public function __construct(
        public readonly bool $manual = false,
        public readonly ?int $userId = null,
        public readonly ?int $farmId = null,
    ) {
    }
}
