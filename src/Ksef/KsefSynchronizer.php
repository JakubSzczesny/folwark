<?php

declare(strict_types=1);

namespace App\Ksef;

use App\Audit\AuditLogger;
use App\Contractor\WhiteListEnricher;
use App\Entity\Contractor;
use App\Entity\Invoice;
use App\Entity\KsefSync;
use App\Entity\User;
use App\Enum\NotificationPriority;
use App\Enum\NotificationType;
use App\Invoice\InvoiceImporter;
use App\Ksef\Dto\MetadataQuery;
use App\Ksef\Event\KsefSyncCompleted;
use App\Message\FetchInvoiceXml;
use App\Notification\Notifier;
use App\Repository\KsefSyncRepository;
use App\Settings\SettingKey;
use App\Settings\Settings;
use App\Twig\FormatExtension;
use Doctrine\ORM\EntityManagerInterface;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * Pobiera metadane nowych faktur z KSeF.
 *
 * Zgodnie z zaleceniem dokumentacji pobieramy przyrostowo po dacie trwałego zapisu (PermanentStorage),
 * rosnąco, osobno dla roli sprzedawcy i nabywcy. Okno zapytania ma najwyżej 90 dni (limit API to 100),
 * a kolejna synchronizacja zaczyna od zapamiętanego punktu minus zakładka czasowa.
 */
class KsefSynchronizer
{
    private const int LOCK_TTL = 1800;

    public function __construct(
        private readonly KsefClient $client,
        private readonly InvoiceImporter $importer,
        private readonly Settings $settings,
        private readonly KsefIdentity $identity,
        private readonly EntityManagerInterface $em,
        private readonly KsefSyncRepository $syncs,
        private readonly Notifier $notifier,
        private readonly MessageBusInterface $bus,
        private readonly EventDispatcherInterface $events,
        private readonly WhiteListEnricher $whiteList,
        private readonly LockFactory $locks,
        private readonly AuditLogger $audit,
        private readonly UrlGeneratorInterface $urls,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function isConfigured(): bool
    {
        return null !== $this->identity->nip() && $this->client->isConfigured();
    }

    /**
     * Czy minął interwał od ostatniej synchronizacji i nie obowiązuje blokada po przekroczeniu limitu.
     */
    public function isDue(\DateTimeImmutable $now = new \DateTimeImmutable()): bool
    {
        $blockedUntil = $this->settings->string(SettingKey::KSEF_BLOCKED_UNTIL);
        if (null !== $blockedUntil && new \DateTimeImmutable($blockedUntil) > $now) {
            return false;
        }

        $last = $this->syncs->findLastStarted();
        if (null === $last) {
            return true;
        }

        $interval = max(5, $this->settings->int(SettingKey::KSEF_SYNC_INTERVAL));

        return $last->getStartedAt()->modify(\sprintf('+%d minutes', $interval)) <= $now;
    }

    public function sync(bool $manual = false, ?User $user = null): ?KsefSync
    {
        $lock = $this->locks->createLock('ksef-sync', self::LOCK_TTL);
        if (!$lock->acquire()) {
            return null;
        }

        try {
            return $this->run($manual, $user);
        } finally {
            $lock->release();
        }
    }

    private function run(bool $manual, ?User $user): KsefSync
    {
        $this->importer->reset();
        $sync = new KsefSync($manual, $user);
        $this->em->persist($sync);
        $this->em->flush();

        /** @var list<Invoice> $new */
        $new = [];
        /** @var list<string> $errors */
        $errors = [];
        /** @var list<string> $rejected */
        $rejected = [];

        try {
            if (!$this->isConfigured()) {
                throw new KsefException('Uzupełnij NIP gospodarstwa i token w ustawieniach KSeF.');
            }

            // Każda rola ma własny kursor, więc błąd jednej nie wstrzymuje drugiej. Wyjątek: limit zapytań
            // i zamknięty EntityManager – wtedy dalsze zapytania i tak by się nie udały.
            foreach ([SubjectType::Buyer, SubjectType::Seller] as $subject) {
                try {
                    $this->syncSubject($subject, $new, $rejected);
                } catch (KsefRateLimitException $e) {
                    throw $e;
                } catch (\Throwable $e) {
                    if (!$this->em->isOpen()) {
                        throw $e;
                    }
                    $this->logger->error('Synchronizacja KSeF ({subject}) nie powiodła się.', ['subject' => $subject->value, 'exception' => $e]);
                    $errors[] = $this->describe($e);
                }
            }
            $this->settings->set(SettingKey::KSEF_BLOCKED_UNTIL, null, false);
        } catch (KsefRateLimitException $e) {
            $this->settings->set(SettingKey::KSEF_BLOCKED_UNTIL, (new \DateTimeImmutable(\sprintf('+%d seconds', $e->retryAfterSeconds)))->format(\DATE_ATOM), false);
            $errors[] = $e->getMessage();
        } catch (\Throwable $e) {
            $this->logger->error('Synchronizacja KSeF nie powiodła się.', ['exception' => $e]);
            $errors[] = $this->describe($e);
        }

        if ([] !== $rejected) {
            $errors[] = \sprintf(
                'Nie udało się odczytać %d %s z KSeF: %s. Pozostałe pobrano, a %s będziemy próbować pobrać ponownie przy każdej synchronizacji.',
                \count($rejected),
                FormatExtension::plural(\count($rejected), 'faktury', 'faktur', 'faktur'),
                implode('; ', $rejected),
                1 === \count($rejected) ? 'tę' : 'te',
            );
        }
        $error = [] !== $errors ? implode(' ', array_unique($errors)) : null;

        $costCount = \count(array_filter($new, static fn (Invoice $i): bool => $i->isCost()));
        $sync->finish(\count($new), $costCount, $error);
        $this->audit->log('KsefSync', $sync->getId(), null === $error ? 'finished' : 'failed', array_filter([
            'manual' => $manual,
            'new' => \count($new),
            'error' => $error,
        ]), $user);
        $this->em->flush();

        $this->enrichContractors($new);
        $this->queueXmlDownloads($new);
        $this->notifyNewInvoices($sync, $new);
        if (null !== $error) {
            $this->notifyError($sync);
        }

        $this->events->dispatch(new KsefSyncCompleted($sync, $new));

        return $sync;
    }

    /**
     * Nowe faktury trafiają do $new na bieżąco, żeby te zapisane przed ewentualnym błędem też dostały powiadomienie i XML.
     *
     * @param list<Invoice> $new
     * @param list<string>  $rejected pozycje, których metadanych nie udało się odczytać
     */
    private function syncSubject(SubjectType $subject, array &$new, array &$rejected): void
    {
        $cursors = (array) $this->settings->get(SettingKey::KSEF_CURSORS);
        $overlap = $this->settings->int(SettingKey::KSEF_OVERLAP_MINUTES);
        $from = isset($cursors[$subject->value])
            ? (new \DateTimeImmutable((string) $cursors[$subject->value]))->modify(\sprintf('-%d minutes', $overlap))
            : ($this->settings->date(SettingKey::KSEF_SYNC_FROM) ?? new \DateTimeImmutable('first day of january this year'))->setTime(0, 0);

        $now = new \DateTimeImmutable();
        // Początek pierwszego zapytania, w którym trafiła się nieczytelna pozycja – kursor go nie minie,
        // więc następna synchronizacja odczyta ją ponownie (już pobrane pomija deduplikacja po numerze KSeF).
        $hold = null;

        while ($from < $now) {
            $windowEnd = $from->modify(\sprintf('+%d days', MetadataQuery::MAX_RANGE_DAYS));
            $isLast = $windowEnd >= $now;
            $query = new MetadataQuery($subject, $from, $isLast ? null : $windowEnd);
            $page = 0;

            while (true) {
                $result = $this->client->queryMetadata($query->withPage($page));
                foreach ($result->invoices as $metadata) {
                    try {
                        $invoice = $this->importer->import($metadata, $subject);
                    } catch (\Throwable $e) {
                        throw new KsefException(\sprintf('Nie udało się zapisać faktury %s (numer KSeF %s): %s', $metadata->invoiceNumber, $metadata->ksefNumber, $e->getMessage()), previous: $e);
                    }
                    if (null !== $invoice) {
                        $new[] = $invoice;
                    }
                }
                if ([] !== $result->rejected) {
                    array_push($rejected, ...$result->rejected);
                    $hold ??= $query->from;
                }
                $this->em->flush();

                if ($result->isTruncated) {
                    $last = $result->lastStorageDate();
                    if (null === $last || $last <= $query->from) {
                        break;
                    }
                    $query = $query->withFrom($last);
                    $page = 0;
                    continue;
                }
                if (!$result->hasMore) {
                    break;
                }
                ++$page;
            }

            $cursor = $result->permanentStorageHwmDate ?? ($isLast ? $now : $windowEnd);
            // HWM dotyczy całego KSeF, nie okna – zwykle to „prawie teraz”. Gdyby kursor wcześniejszego okna
            // tyle przeskoczył, przerwanie w kolejnym oknie (limit, awaria) pominęłoby jego faktury na zawsze.
            if (!$isLast && $cursor > $windowEnd) {
                $cursor = $windowEnd;
            }
            if (null !== $hold && $hold < $cursor) {
                $cursor = $hold;
            }
            $cursors[$subject->value] = $cursor->format(\DATE_ATOM);
            $this->settings->set(SettingKey::KSEF_CURSORS, $cursors, false);
            $this->em->flush();

            if ($isLast) {
                break;
            }
            $from = $windowEnd;
        }
    }

    private function describe(\Throwable $e): string
    {
        return $e instanceof KsefException ? $e->getMessage() : 'Nieoczekiwany błąd: '.$e->getMessage();
    }

    /**
     * @param list<Invoice> $invoices
     */
    private function enrichContractors(array $invoices): void
    {
        if (!$this->whiteList->isEnabled()) {
            return;
        }

        $checked = [];
        foreach ($invoices as $invoice) {
            $contractor = $invoice->getContractor();
            if (!$contractor instanceof Contractor || isset($checked[spl_object_id($contractor)])) {
                continue;
            }
            $checked[spl_object_id($contractor)] = true;
            if ($this->whiteList->needsEnrichment($contractor)) {
                $this->whiteList->enrichQuietly($contractor);
            }
        }
        $this->em->flush();
    }

    /**
     * @param list<Invoice> $invoices
     */
    private function queueXmlDownloads(array $invoices): void
    {
        if (!$this->settings->bool(SettingKey::KSEF_AUTO_FETCH_XML)) {
            return;
        }

        foreach ($invoices as $invoice) {
            $farmId = $invoice->getFarm()?->getId();
            if ($invoice->isCost() && null !== $invoice->getId() && null !== $farmId) {
                $this->bus->dispatch(new FetchInvoiceXml($invoice->getId(), $farmId));
            }
        }
    }

    /**
     * @param list<Invoice> $invoices
     */
    private function notifyNewInvoices(KsefSync $sync, array $invoices): void
    {
        if ([] === $invoices) {
            return;
        }

        $cost = \count(array_filter($invoices, static fn (Invoice $i): bool => $i->isCost()));
        $income = \count($invoices) - $cost;
        $parts = [];
        if ($cost > 0) {
            $parts[] = \sprintf('%d %s %s', $cost, FormatExtension::plural($cost, 'nowa', 'nowe', 'nowych'), FormatExtension::plural($cost, 'faktura zakupowa', 'faktury zakupowe', 'faktur zakupowych'));
        }
        if ($income > 0) {
            $parts[] = \sprintf('%d %s %s', $income, FormatExtension::plural($income, 'nowa', 'nowe', 'nowych'), FormatExtension::plural($income, 'faktura sprzedażowa', 'faktury sprzedażowe', 'faktur sprzedażowych'));
        }

        $this->notifier->notify(
            NotificationType::NewInvoices,
            NotificationPriority::Info,
            ucfirst(implode(', ', $parts)),
            null,
            1 === \count($invoices)
                ? $this->urls->generate('app_invoice_show', ['id' => $invoices[0]->getId()])
                : $this->urls->generate('app_invoice_index', ['rodzaj' => $cost > 0 ? 'zakupowe' : 'sprzedazowe', 'status' => 'new']),
            'ksef-sync:'.$sync->getId(),
        );
    }

    private function notifyError(KsefSync $sync): void
    {
        $streakStart = $this->syncs->findErrorStreakStart() ?? $sync->getStartedAt();
        $repeatHours = max(1, $this->settings->int(SettingKey::NOTIFY_KSEF_REPEAT_HOURS));
        $hours = intdiv(time() - $streakStart->getTimestamp(), 3600);

        $this->notifier->notify(
            NotificationType::KsefError,
            NotificationPriority::Urgent,
            'Błąd synchronizacji KSeF',
            $sync->getError(),
            $this->urls->generate('app_settings_ksef'),
            \sprintf('ksef-error:%s:%d', $streakStart->format('YmdHis'), intdiv($hours, $repeatHours)),
        );
    }
}
