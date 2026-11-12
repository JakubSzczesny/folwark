<?php

declare(strict_types=1);

namespace App\Gmail;

use App\Entity\Contractor;
use App\Entity\ContractorMail;
use App\Farm\FarmContext;
use App\Repository\ContractorMailRepository;
use App\Repository\ContractorRepository;
use App\Settings\SettingKey;
use App\Settings\Settings;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Indeks korespondencji z kontrahentami w bazie: nagłówki maili ze skrzynki Gmail
 * przypisane do kontrahentów po adresie albo domenie e-mail. Treść zostaje w Gmailu.
 *
 * Każda synchronizacja pobiera z Gmaila listę UID-ów korespondencji (szybkie wyszukiwanie),
 * usuwa z indeksu maile skasowane w Gmailu i dopisuje nagłówki nowych. Gdy zmieni się
 * skrzynka albo adresy kontrahentów, indeks budujemy od nowa.
 */
class MailIndexer
{
    /** Ile nagłówków pobieramy z Gmaila jednym poleceniem. */
    private const int CHUNK = 200;

    /** Zmiana wymusza przebudowę indeksu, np. gdy dochodzi nowa kolumna do uzupełnienia. */
    private const int INDEX_VERSION = 2;

    public function __construct(
        private readonly GmailMailbox $mailbox,
        private readonly ContractorRepository $contractors,
        private readonly ContractorMailRepository $mails,
        private readonly Settings $settings,
        private readonly FarmContext $context,
        private readonly EntityManagerInterface $em,
    ) {
    }

    public function isConfigured(): bool
    {
        return $this->mailbox->isConfigured();
    }

    /**
     * Czy minęło dość czasu od ostatniej synchronizacji (dla harmonogramu).
     */
    public function isDue(int $minutes): bool
    {
        $syncedAt = $this->syncedAt();

        return null === $syncedAt || $syncedAt < new \DateTimeImmutable(\sprintf('-%d minutes', $minutes - 1));
    }

    public function syncedAt(): ?\DateTimeImmutable
    {
        $at = $this->state()['synced_at'] ?? null;

        return \is_string($at) ? new \DateTimeImmutable($at) : null;
    }

    public function lastError(): ?string
    {
        $error = $this->state()['error'] ?? null;

        return \is_string($error) ? $error : null;
    }

    /**
     * @return int liczba nowych wpisów
     */
    public function sync(): int
    {
        $farmId = $this->context->farmId() ?? throw new \LogicException('Indeks poczty buduje się w kontekście gospodarstwa.');
        if (!$this->mailbox->isConfigured()) {
            return 0;
        }

        try {
            $added = $this->doSync($farmId);
        } catch (\Throwable $e) {
            $this->saveState(['error' => $e->getMessage()] + $this->state());

            throw $e;
        }

        return $added;
    }

    /**
     * Po odłączeniu skrzynki indeks jest nieaktualny.
     */
    public function clear(): void
    {
        $farmId = $this->context->farmId() ?? throw new \LogicException('Brak gospodarstwa.');
        $this->mails->deleteAll($farmId);
        $this->settings->set(SettingKey::GMAIL_INDEX, [], false);
    }

    /**
     * Kontrahenci według adresów i domen, po których trafiają do nich maile. Adres samej
     * skrzynki pomijamy – wszystkie maile w niej go zawierają.
     *
     * @return array<string, list<Contractor>>
     */
    public function contractorsByMatcher(): array
    {
        $own = (string) $this->mailbox->address();
        $byMatcher = [];
        foreach ($this->contractors->findWithEmail() as $contractor) {
            foreach ($contractor->getMailMatchers() as $matcher) {
                if (!MailMatchers::matches($own, [$matcher])) {
                    $byMatcher[$matcher][] = $contractor;
                }
            }
        }

        return $byMatcher;
    }

    /**
     * Kontrahenci, do których należy mail o podanych uczestnikach.
     *
     * @param array<string, list<Contractor>> $byMatcher
     * @param list<string>                    $participants
     *
     * @return array<int, Contractor> id kontrahenta => kontrahent
     */
    public static function contractorsOf(array $byMatcher, array $participants): array
    {
        $found = [];
        foreach ($participants as $address) {
            foreach (MailMatchers::candidates($address) as $candidate) {
                foreach ($byMatcher[$candidate] ?? [] as $contractor) {
                    $found[(int) $contractor->getId()] = $contractor;
                }
            }
        }

        return $found;
    }

    private function doSync(int $farmId): int
    {
        $byMatcher = $this->contractorsByMatcher();
        $matchers = array_map('strval', array_keys($byMatcher));

        $signature = hash('xxh128', json_encode([
            self::INDEX_VERSION,
            $this->mailbox->address(),
            $this->mailbox->uidValidity(),
            array_map(static fn (array $list): array => array_map(static fn (Contractor $c): ?int => $c->getId(), $list), $byMatcher),
        ], \JSON_THROW_ON_ERROR));

        $state = $this->state();
        if (($state['signature'] ?? null) !== $signature) {
            $this->mails->deleteAll($farmId);
            $indexed = [];
            $skipped = [];
        } else {
            $indexed = $this->mails->indexedUids();
            $skipped = array_values(array_map('intval', (array) ($state['skipped'] ?? [])));
        }

        $current = [] === $matchers ? [] : $this->mailbox->uids($matchers);
        $removed = array_values(array_diff($indexed, $current));
        if ([] !== $removed) {
            $this->mails->deleteUids($farmId, $removed);
        }

        $new = array_values(array_diff($current, $indexed, $skipped));
        $skipped = array_values(array_intersect($skipped, $current));
        $added = 0;
        if ([] !== $new) {
            $withAttachments = $this->mailbox->uids($matchers, 'has:attachment');
            foreach (array_chunk($new, self::CHUNK) as $chunk) {
                foreach ($this->mailbox->summaries($chunk, $matchers, $withAttachments) as $summary) {
                    $stored = $this->store($summary, $byMatcher);
                    $added += $stored;
                    // Gmail szuka szerzej (np. domeny także w nazwie nadawcy) – takiego maila nie pobieramy co synchronizację.
                    if (0 === $stored) {
                        $skipped[] = $summary->uid;
                    }
                }
                $this->em->flush();
            }
        }

        $this->saveState(['signature' => $signature, 'synced_at' => (new \DateTimeImmutable())->format(\DATE_ATOM), 'error' => null, 'skipped' => $skipped]);

        return $added;
    }

    /**
     * Jeden wpis na każdego kontrahenta z adresem lub domeną wśród nadawcy i adresatów.
     *
     * @param array<string, list<Contractor>> $byMatcher
     */
    private function store(MailSummary $summary, array $byMatcher): int
    {
        $contractors = self::contractorsOf($byMatcher, $summary->participants);
        foreach ($contractors as $contractor) {
            $incoming = MailMatchers::matches($summary->fromAddress, $contractor->getMailMatchers());
            $this->em->persist(new ContractorMail($contractor, $summary->uid, $summary, $incoming));
        }

        return \count($contractors);
    }

    /**
     * @return array<string, mixed>
     */
    private function state(): array
    {
        $state = $this->settings->get(SettingKey::GMAIL_INDEX);

        return \is_array($state) ? $state : [];
    }

    /**
     * @param array<string, mixed> $state
     */
    private function saveState(array $state): void
    {
        $this->settings->set(SettingKey::GMAIL_INDEX, $state);
    }
}
