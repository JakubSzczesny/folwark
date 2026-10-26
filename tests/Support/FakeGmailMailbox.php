<?php

declare(strict_types=1);

namespace App\Tests\Support;

use App\Crypto\SecretBox;
use App\Gmail\GmailMailbox;
use App\Gmail\MailMessage;
use App\Gmail\MailParser;
use App\Settings\Settings;
use App\Ui\Pager;
use Webklex\PHPIMAP\Config;
use Webklex\PHPIMAP\Message;

/**
 * Skrzynka z wiadomościami z plików .eml zamiast połączenia z Gmailem.
 */
final class FakeGmailMailbox extends GmailMailbox
{
    /** @var array<int, Message> UID => wiadomość */
    private array $messages = [];

    public function __construct(Settings $settings, SecretBox $secrets, private readonly MailParser $mailParser)
    {
        parent::__construct($settings, $secrets, $mailParser);
    }

    public function add(int $uid, string $eml): void
    {
        $this->messages[$uid] = Message::fromString($eml, Config::make(['decoding' => self::DECODING]))->setUid($uid);
    }

    public function isConfigured(): bool
    {
        return true;
    }

    public function address(): string
    {
        return 'folwark@gmail.com';
    }

    public function test(): string
    {
        return 'Połączono (test).';
    }

    public function conversation(array $addresses, int $page = 1): Pager
    {
        $items = [];
        foreach (array_reverse($this->messages, true) as $message) {
            if ($this->mailParser->involves($message, $addresses)) {
                $items[] = $this->mailParser->summary($message, $addresses, $message->hasAttachments());
            }
        }

        return Pager::fromArray($items, $page, self::PER_PAGE);
    }

    public function message(int $uid, array $addresses): ?MailMessage
    {
        $message = $this->messages[$uid] ?? null;

        return null !== $message && $this->mailParser->involves($message, $addresses) ? $this->mailParser->message($message, $addresses) : null;
    }
}
