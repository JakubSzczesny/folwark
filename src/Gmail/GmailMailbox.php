<?php

declare(strict_types=1);

namespace App\Gmail;

use App\Crypto\SecretBox;
use App\Settings\SettingKey;
use App\Settings\Settings;
use Symfony\Contracts\Service\ResetInterface;
use Webklex\PHPIMAP\Client;
use Webklex\PHPIMAP\ClientManager;
use Webklex\PHPIMAP\Config;
use Webklex\PHPIMAP\Connection\Protocols\ImapProtocol;
use Webklex\PHPIMAP\Exceptions\AuthFailedException;
use Webklex\PHPIMAP\Exceptions\ConnectionFailedException;
use Webklex\PHPIMAP\Message;

/**
 * Skrzynka Gmail gospodarstwa przez IMAP z hasłem do aplikacji – tylko do odczytu.
 *
 * Szukamy w folderze „Wszystkie” (atrybut \All), więc lista obejmuje odebrane
 * i wysłane, bez spamu i kosza. Folder otwieramy przez EXAMINE, dzięki czemu
 * Folwark nie zmienia flag (np. „przeczytane”) w skrzynce.
 */
class GmailMailbox implements ResetInterface
{
    private const string HOST = 'imap.gmail.com';

    /** Dłuższego filtra adresów nie wysyłamy do Gmaila – szukamy wtedy bez niego. */
    private const int MAX_FILTER_LENGTH = 6000;

    /** Nagłówki bez polskich znaków dekodujemy przez iconv – rozszerzenie imap nie jest wymagane. */
    public const array DECODING = ['options' => ['header' => 'iconv', 'message' => 'iconv', 'attachment' => 'iconv']];

    private ?Client $client = null;
    private ?string $folder = null;

    public function __construct(
        private readonly Settings $settings,
        private readonly SecretBox $secrets,
        private readonly MailParser $parser,
    ) {
    }

    public function isConfigured(): bool
    {
        return null !== $this->settings->string(SettingKey::GMAIL_ADDRESS)
            && null !== $this->settings->string(SettingKey::GMAIL_APP_PASSWORD);
    }

    public function address(): ?string
    {
        return $this->settings->string(SettingKey::GMAIL_ADDRESS);
    }

    /**
     * Logowanie i odszukanie folderu „Wszystkie”; zwraca opis do komunikatu.
     */
    public function test(): string
    {
        $folder = $this->open();
        $status = $this->connection()->examineFolder($folder)->validatedData();

        return \sprintf('Połączono ze skrzynką %s – w folderze „%s” jest %d wiadomości.', $this->address(), $folder, (int) ($status['exists'] ?? 0));
    }

    /**
     * UIDVALIDITY folderu „Wszystkie” – gdy się zmieni, dotychczasowe UID-y są nieważne.
     */
    public function uidValidity(): int
    {
        $status = $this->connection()->examineFolder($this->open())->validatedData();

        return (int) ($status['uidvalidity'] ?? 0);
    }

    /**
     * UID-y wiadomości od, do i DW podanych adresów (rosnąco). Adresy dzielimy na porcje,
     * żeby filtr nie przekroczył długości, którą przyjmuje Gmail.
     *
     * @param list<string> $addresses
     *
     * @return list<int>
     */
    public function uids(array $addresses, string $extra = ''): array
    {
        $this->open();
        $uids = [];
        $chunk = [];
        foreach ($addresses as $address) {
            $chunk[] = $address;
            if (\strlen(self::gmailFilter($chunk)) > self::MAX_FILTER_LENGTH) {
                array_pop($chunk);
                $uids = [...$uids, ...$this->gmailSearch(trim(self::gmailFilter($chunk).' '.$extra))];
                $chunk = [$address];
            }
        }
        if ([] !== $chunk) {
            $uids = [...$uids, ...$this->gmailSearch(trim(self::gmailFilter($chunk).' '.$extra))];
        }

        $uids = array_values(array_unique($uids));
        sort($uids);

        return $uids;
    }

    /**
     * Nagłówki wiadomości o podanych UID-ach (bez treści, bez zmiany flag).
     *
     * @param list<int>    $uids
     * @param list<string> $addresses adresy kontrahentów – do pola „przyszła od kontrahenta”
     * @param list<int>    $withAttachments
     *
     * @return list<MailSummary>
     */
    public function summaries(array $uids, array $addresses, array $withAttachments = []): array
    {
        $this->open();
        $attachments = array_flip($withAttachments);

        return array_map(
            fn (Message $message): MailSummary => $this->parser->summary($message, $addresses, isset($attachments[(int) $message->getUid()])),
            $this->headers($uids),
        );
    }

    /**
     * Wyszukiwanie w korespondencji z podanymi adresami (składnia wyszukiwarki Gmaila), od najnowszych.
     *
     * @param list<string> $addresses
     *
     * @return list<MailSummary>
     */
    public function search(string $query, array $addresses, int $limit): array
    {
        $query = trim(str_replace(['{', '}'], ' ', $query));
        if ('' === $query || [] === $addresses) {
            return [];
        }

        $this->open();
        $filter = self::gmailFilter($addresses);
        // Bardzo długiego filtra Gmail nie przyjmie – wtedy szukamy w całej skrzynce
        // i zostawiamy tylko wiadomości kontrahentów.
        $long = \strlen($filter) > self::MAX_FILTER_LENGTH;
        $uids = \array_slice(array_reverse($this->gmailSearch($long ? $query : $filter.' '.$query)), 0, $long ? $limit * 5 : $limit);

        $results = [];
        foreach ($this->headers($uids) as $message) {
            if ($long && !$this->parser->involves($message, $addresses)) {
                continue;
            }
            $results[] = $this->parser->summary($message, $addresses);
            if (\count($results) >= $limit) {
                break;
            }
        }

        return $results;
    }

    /**
     * Pełna wiadomość, o ile dotyczy któregoś z adresów kontrahenta (inaczej null).
     *
     * @param list<string> $addresses
     */
    public function message(int $uid, array $addresses): ?MailMessage
    {
        if ([] === $addresses) {
            return null;
        }

        $this->open();
        $raw = $this->connection()->fetch(['UID', 'BODY.PEEK[]'], [$uid])->validatedData()[$uid]['BODY[]'] ?? null;
        if (!\is_string($raw) || '' === $raw) {
            return null;
        }

        $message = Message::fromString($raw, Config::make(['decoding' => self::DECODING]));
        if (!$this->parser->involves($message, $addresses)) {
            return null;
        }
        $message->setUid($uid);

        return $this->parser->message($message, $addresses);
    }

    /**
     * Filtr wyszukiwarki Gmaila: {…} to „lub”.
     *
     * @param list<string> $addresses
     */
    public static function gmailFilter(array $addresses): string
    {
        $terms = [];
        foreach ($addresses as $address) {
            if (false === filter_var($address, \FILTER_VALIDATE_EMAIL) || 1 === preg_match('/["\\\\{}\s]/', $address)) {
                continue;
            }
            foreach (['from', 'to', 'cc'] as $field) {
                $terms[] = $field.':'.$address;
            }
        }

        return '{'.implode(' ', $terms).'}';
    }

    /**
     * @return list<int>
     */
    private function gmailSearch(string $gmailQuery): array
    {
        // Zapytanie z polskimi znakami musi iść jako literał IMAP ({długość} + dane) z CHARSET UTF-8.
        $argument = 1 === preg_match('/[^\x20-\x7E]/', $gmailQuery)
            ? ['{'.\strlen($gmailQuery).'}', $gmailQuery]
            : $this->quote($gmailQuery);
        $uids = $this->connection()->search(['CHARSET', 'UTF-8', 'X-GM-RAW', $argument])->validatedData();

        return array_values(array_map('intval', (array) $uids));
    }

    /**
     * Same nagłówki przez BODY.PEEK i własne parsowanie – zapytania biblioteki po pobraniu
     * wiadomości ustawiają flagę \Seen (STORE), a skrzynki nie zmieniamy. Kolejność jak w $uids.
     *
     * @param list<int> $uids
     *
     * @return list<Message>
     */
    private function headers(array $uids): array
    {
        if ([] === $uids) {
            return [];
        }

        $headers = $this->connection()->fetch(['UID', 'BODY.PEEK[HEADER]'], $uids)->validatedData();
        $config = Config::make(['decoding' => self::DECODING]);

        $messages = [];
        foreach ($uids as $uid) {
            $header = $headers[$uid]['BODY[HEADER]'] ?? null;
            if (\is_string($header) && '' !== trim($header)) {
                $messages[] = Message::fromString(rtrim($header)."\r\n\r\n", $config)->setUid($uid);
            }
        }

        return $messages;
    }

    /**
     * Łączy się i otwiera folder „Wszystkie” tylko do odczytu; zwraca jego nazwę.
     */
    private function open(): string
    {
        if (null !== $this->folder) {
            return $this->folder;
        }

        $client = $this->client();
        $folders = $this->connection()->folders('', '*')->validatedData();
        foreach ($folders as $name => $folder) {
            if (\in_array('\\All', (array) ($folder['flags'] ?? []), true)) {
                $this->folder = (string) $name;
                break;
            }
        }
        if (null === $this->folder) {
            throw new GmailException('Nie znaleziono folderu „Wszystkie” w skrzynce. Sprawdź w Gmailu (Ustawienia → Etykiety), czy „Wszystkie” jest widoczne w IMAP.');
        }

        $this->connection()->examineFolder($this->folder)->validatedData();
        // Biblioteka przy każdej wiadomości robi SELECT bieżącego folderu – oznaczamy go
        // jako otwarty, żeby został w trybie tylko do odczytu.
        $client->setActiveFolder($this->folder);

        return $this->folder;
    }

    private function client(): Client
    {
        if (null !== $this->client) {
            return $this->client;
        }

        $address = $this->address();
        $encrypted = $this->settings->string(SettingKey::GMAIL_APP_PASSWORD);
        if (null === $address || null === $encrypted) {
            throw new GmailException('Skrzynka Gmail nie jest podłączona – uzupełnij ją w Ustawieniach → Gmail.');
        }

        $client = (new ClientManager(['decoding' => self::DECODING, 'options' => ['soft_fail' => true]]))->make([
            'host' => self::HOST,
            'port' => 993,
            'encryption' => 'ssl',
            'validate_cert' => true,
            'protocol' => 'imap',
            'username' => $address,
            'password' => str_replace(' ', '', $this->secrets->decrypt($encrypted)),
            'authentication' => null,
            'timeout' => 20,
        ]);

        try {
            $client->connect();
        } catch (AuthFailedException) {
            throw new GmailException('Gmail odrzucił logowanie. Sprawdź adres i hasło do aplikacji (to nie jest zwykłe hasło do konta Google).');
        } catch (ConnectionFailedException $e) {
            $previous = $e->getPrevious();
            if ($previous instanceof AuthFailedException || str_contains(mb_strtolower($e->getMessage().' '.$previous?->getMessage()), 'auth')) {
                throw new GmailException('Gmail odrzucił logowanie. Sprawdź adres i hasło do aplikacji (to nie jest zwykłe hasło do konta Google).');
            }
            throw new GmailException('Nie udało się połączyć z Gmailem: '.$e->getMessage());
        }

        return $this->client = $client;
    }

    private function connection(): ImapProtocol
    {
        $connection = $this->client()->getConnection();
        if (!$connection instanceof ImapProtocol) {
            throw new GmailException('Nieobsługiwany protokół skrzynki.');
        }

        return $connection;
    }

    private function quote(string $value): string
    {
        return '"'.addcslashes($value, '"\\').'"';
    }

    public function reset(): void
    {
        try {
            $this->client?->disconnect();
        } catch (\Throwable) {
        }
        $this->client = null;
        $this->folder = null;
    }

    public function __destruct()
    {
        $this->reset();
    }
}
