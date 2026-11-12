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
        /** CSS z bloków <style> maila (App\Gmail\MailParser::styles). */
        public string $styles = '',
    ) {
    }

    public function gmailUrl(?string $mailbox): ?string
    {
        return GmailLink::message($this->messageId, $mailbox);
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
