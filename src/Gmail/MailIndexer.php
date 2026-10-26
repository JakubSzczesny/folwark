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
 * przypisane do kontrahentów po adresie e-mail. Treść zostaje w Gmailu.
 *
 * Każda synchronizacja pobiera z Gmaila listę UID-ów korespondencji (szybkie wyszukiwanie),
 * usuwa z indeksu maile skasowane w Gmailu i dopisuje nagłówki nowych. Gdy zmieni się
 * skrzynka albo adresy kontrahentów, indeks budujemy od nowa.
 */
class MailIndexer
{
    /** Ile nagłówków pobieramy z Gmaila jednym poleceniem. */
    private const int CHUNK = 200;

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

    private function doSync(int $farmId): int
    {
        /** @var array<string, list<Contractor>> $byAddress */
        $byAddress = [];
        foreach ($this->contractors->findWithEmail() as $contractor) {
            foreach ($contractor->getEmailAddresses() as $address) {
                $byAddress[$address][] = $contractor;
            }
        }
        $addresses = array_map('strval', array_keys($byAddress));

        $signature = hash('xxh128', json_encode([
            $this->mailbox->address(),
            $this->mailbox->uidValidity(),
            array_map(static fn (array $list): array => array_map(static fn (Contractor $c): ?int => $c->getId(), $list), $byAddress),
        ], \JSON_THROW_ON_ERROR));

        $state = $this->state();
        if (($state['signature'] ?? null) !== $signature) {
            $this->mails->deleteAll($farmId);
            $indexed = [];
        } else {
            $indexed = $this->mails->indexedUids();
        }

        $current = [] === $addresses ? [] : $this->mailbox->uids($addresses);
        $removed = array_values(array_diff($indexed, $current));
        if ([] !== $removed) {
            $this->mails->deleteUids($farmId, $removed);
        }

        $new = array_values(array_diff($current, $indexed));
        $added = 0;
        if ([] !== $new) {
            $withAttachments = $this->mailbox->uids($addresses, 'has:attachment');
            foreach (array_chunk($new, self::CHUNK) as $chunk) {
                foreach ($this->mailbox->summaries($chunk, $addresses, $withAttachments) as $summary) {
                    $added += $this->store($summary, $byAddress);
                }
                $this->em->flush();
            }
        }

        $this->saveState(['signature' => $signature, 'synced_at' => (new \DateTimeImmutable())->format(\DATE_ATOM), 'error' => null]);

        return $added;
    }

    /**
     * Jeden wpis na każdego kontrahenta z adresem wśród nadawcy i adresatów.
     *
     * @param array<string, list<Contractor>> $byAddress
     */
    private function store(MailSummary $summary, array $byAddress): int
    {
        $stored = [];
        foreach ($summary->participants as $address) {
            foreach ($byAddress[$address] ?? [] as $contractor) {
                $id = (int) $contractor->getId();
                if (isset($stored[$id])) {
                    continue;
                }
                $stored[$id] = true;
                $incoming = \in_array($summary->fromAddress, $contractor->getEmailAddresses(), true);
                $this->em->persist(new ContractorMail($contractor, $summary->uid, $summary, $incoming));
            }
        }

        return \count($stored);
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
