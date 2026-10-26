<?php

declare(strict_types=1);

namespace App\Gmail;

/**
 * Wiadomość na liście korespondencji – same nagłówki, bez treści.
 */
final readonly class MailSummary
{
    /**
     * @param list<string> $to
     * @param list<string> $participants
     */
    public function __construct(
        public int $uid,
        public string $subject,
        public string $from,
        public string $fromAddress,
        public array $to,
        public ?\DateTimeImmutable $date,
        /** true – przyszła od kontrahenta, false – wysłana do niego */
        public bool $incoming,
        public bool $hasAttachments,
        /** adresy z Od, Do i DW, małymi literami */
        public array $participants = [],
    ) {
    }
}
