<?php

declare(strict_types=1);

namespace App\Tests\Support;

use App\Crypto\SecretBox;
use App\Gmail\GmailMailbox;
use App\Gmail\MailMessage;
use App\Gmail\MailParser;
use App\Settings\Settings;
use Webklex\PHPIMAP\Config;
use Webklex\PHPIMAP\Message;

/**
 * Skrzynka z wiadomościami z plików .eml zamiast połączenia z Gmailem.
 */
final class FakeGmailMailbox extends GmailMailbox
{
    public bool $configured = true;

    public int $validity = 1;

    /** @var array<int, Message> UID => wiadomość */
    private array $messages = [];

    public function __construct(Settings $settings, SecretBox $secrets, private readonly MailParser $mailParser)
    {
        parent::__construct($settings, $secrets, $mailParser);
    }

    public function add(int $uid, string $eml): void
    {
        $this->messages[$uid] = Message::fromString($eml, Config::make(['decoding' => self::DECODING]))->setUid($uid);
        ksort($this->messages);
    }

    public function remove(int $uid): void
    {
        unset($this->messages[$uid]);
    }

    public function isConfigured(): bool
    {
        return $this->configured;
    }

    public function address(): string
    {
        return 'folwark@gmail.com';
    }

    public function test(): string
    {
        return 'Połączono (test).';
    }

    public function uidValidity(): int
    {
        return $this->validity;
    }

    public function uids(array $addresses, string $extra = ''): array
    {
        $uids = [];
        foreach ($this->messages as $uid => $message) {
            if ($this->mailParser->involves($message, $addresses) && ('has:attachment' !== $extra || $message->hasAttachments())) {
                $uids[] = $uid;
            }
        }

        return $uids;
    }

    public function summaries(array $uids, array $addresses, array $withAttachments = []): array
    {
        $summaries = [];
        foreach ($uids as $uid) {
            if (isset($this->messages[$uid])) {
                $summaries[] = $this->mailParser->summary($this->messages[$uid], $addresses, \in_array($uid, $withAttachments, true));
            }
        }

        return $summaries;
    }

    public function search(string $query, array $addresses, int $limit): array
    {
        $results = [];
        foreach (array_reverse($this->messages, true) as $message) {
            $text = mb_strtolower($message->getSubject().' '.$message->getTextBody());
            if ($this->mailParser->involves($message, $addresses) && str_contains($text, mb_strtolower($query))) {
                $results[] = $this->mailParser->summary($message, $addresses);
            }
        }

        return \array_slice($results, 0, $limit);
    }

    public function message(int $uid, array $addresses): ?MailMessage
    {
        $message = $this->messages[$uid] ?? null;

        return null !== $message && $this->mailParser->involves($message, $addresses) ? $this->mailParser->message($message, $addresses) : null;
    }
}
