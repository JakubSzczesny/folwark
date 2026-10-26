<?php

declare(strict_types=1);

namespace App\Message;

/**
 * Odświeżenie indeksu korespondencji z Gmaila. Harmonogram wysyła ją bez gospodarstwa –
 * wtedy obsługa przechodzi po gospodarstwach z podłączoną skrzynką, którym minął interwał.
 */
final class SyncGmailIndex implements AsyncMessage
{
    public function __construct(
        public readonly ?int $farmId = null,
    ) {
    }
}
