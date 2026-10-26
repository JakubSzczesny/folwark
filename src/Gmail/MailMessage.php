<?php

declare(strict_types=1);

namespace App\Gmail;

/**
 * Pełna wiadomość do podglądu.
 */
final readonly class MailMessage
{
    /**
     * @param list<string>         $cc
     * @param list<MailAttachment> $attachments załączniki bez obrazków osadzonych w treści (stopki, logotypy)
     */
    public function __construct(
        public MailSummary $summary,
        public array $cc,
        /** Oczyszczony HTML z obrazkami osadzonymi jako data: albo null, gdy wiadomość jest tylko tekstowa. */
        public ?string $html,
        public string $text,
        public array $attachments,
        /** Nagłówek Message-ID bez nawiasów – do linku „Otwórz w Gmailu”. */
        public ?string $messageId = null,
    ) {
    }

    /**
     * Link do tej wiadomości w Gmailu (wyszukanie po Message-ID na koncie skrzynki).
     */
    public function gmailUrl(?string $mailbox): ?string
    {
        if (null === $this->messageId) {
            return null;
        }

        return 'https://mail.google.com/mail/'.(null !== $mailbox ? '?authuser='.rawurlencode($mailbox) : '').'#search/'.rawurlencode('rfc822msgid:'.$this->messageId);
    }

    public function attachment(int $index): ?MailAttachment
    {
        foreach ($this->attachments as $attachment) {
            if ($attachment->index === $index) {
                return $attachment;
            }
        }

        return null;
    }
}
