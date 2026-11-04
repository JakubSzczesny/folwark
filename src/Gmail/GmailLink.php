<?php

declare(strict_types=1);

namespace App\Gmail;

final class GmailLink
{
    /**
     * Link do wiadomości w Gmailu (wyszukanie po Message-ID na koncie skrzynki).
     */
    public static function message(?string $messageId, ?string $mailbox): ?string
    {
        if (null === $messageId) {
            return null;
        }

        return 'https://mail.google.com/mail/'.(null !== $mailbox ? '?authuser='.rawurlencode($mailbox) : '').'#search/'.rawurlencode('rfc822msgid:'.$messageId);
    }
}
