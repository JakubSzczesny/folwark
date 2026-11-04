<?php

declare(strict_types=1);

namespace App\Entity;

use App\Farm\BelongsToFarm;
use App\Farm\FarmScoped;
use App\Gmail\GmailLink;
use App\Gmail\MailSummary;
use App\Repository\ContractorMailRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * Wpis indeksu korespondencji: nagłówki maila ze skrzynki Gmail gospodarstwa przypisane
 * do kontrahenta (App\Gmail\MailIndexer). Treść i załączniki zostają w Gmailu.
 */
#[ORM\Entity(repositoryClass: ContractorMailRepository::class)]
#[ORM\UniqueConstraint(columns: ['farm_id', 'uid', 'contractor_id'])]
#[ORM\Index(columns: ['contractor_id', 'sent_at'])]
class ContractorMail implements FarmScoped
{
    use BelongsToFarm;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private string $subject;

    #[ORM\Column(length: 180)]
    private string $fromName;

    #[ORM\Column(length: 180)]
    private string $fromAddress;

    #[ORM\Column(length: 500)]
    private string $recipients;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $sentAt;

    #[ORM\Column]
    private bool $incoming;

    #[ORM\Column]
    private bool $hasAttachments;

    /** Nagłówek Message-ID – do linku „Otwórz w Gmailu”. */
    #[ORM\Column(length: 255, nullable: true)]
    private ?string $messageId;

    public function __construct(
        #[ORM\ManyToOne]
        #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
        private Contractor $contractor,
        /** UID wiadomości w folderze „Wszystkie” – do podglądu prosto z Gmaila. */
        #[ORM\Column]
        private int $uid,
        MailSummary $summary,
        /** true – mail od tego kontrahenta, false – do niego (albo kontrahent tylko w DW) */
        bool $incoming,
    ) {
        $this->subject = mb_substr($summary->subject, 0, 255);
        $this->fromName = mb_substr($summary->from, 0, 180);
        $this->fromAddress = mb_substr($summary->fromAddress, 0, 180);
        $this->recipients = mb_substr(implode(', ', $summary->to), 0, 500);
        $this->sentAt = $summary->date;
        $this->incoming = $incoming;
        $this->hasAttachments = $summary->hasAttachments;
        $this->messageId = null !== $summary->messageId && mb_strlen($summary->messageId) <= 255 ? $summary->messageId : null;
    }

    public function gmailUrl(?string $mailbox): ?string
    {
        return GmailLink::message($this->messageId, $mailbox);
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getContractor(): Contractor
    {
        return $this->contractor;
    }

    public function getUid(): int
    {
        return $this->uid;
    }

    public function getSubject(): string
    {
        return $this->subject;
    }

    public function getFromName(): string
    {
        return $this->fromName;
    }

    public function getFromAddress(): string
    {
        return $this->fromAddress;
    }

    public function getRecipients(): string
    {
        return $this->recipients;
    }

    public function getSentAt(): ?\DateTimeImmutable
    {
        return $this->sentAt;
    }

    public function isIncoming(): bool
    {
        return $this->incoming;
    }

    public function hasAttachments(): bool
    {
        return $this->hasAttachments;
    }
}
