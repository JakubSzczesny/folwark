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
        return null !== $this->identity->nip();
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
        $error = null;

        try {
            if (!$this->isConfigured()) {
                throw new KsefException('Uzupełnij NIP gospodarstwa i token w ustawieniach KSeF.');
            }

            foreach ([SubjectType::Buyer, SubjectType::Seller] as $subject) {
                array_push($new, ...$this->syncSubject($subject));
            }
            $this->settings->set(SettingKey::KSEF_BLOCKED_UNTIL, null, false);
        } catch (KsefRateLimitException $e) {
            $this->settings->set(SettingKey::KSEF_BLOCKED_UNTIL, (new \DateTimeImmutable(\sprintf('+%d seconds', $e->retryAfterSeconds)))->format(\DATE_ATOM), false);
            $error = $e->getMessage();
        } catch (\Throwable $e) {
            $this->logger->error('Synchronizacja KSeF nie powiodła się.', ['exception' => $e]);
            $error = $e instanceof KsefException ? $e->getMessage() : 'Nieoczekiwany błąd: '.$e->getMessage();
        }

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
     * @return list<Invoice>
     */
    private function syncSubject(SubjectType $subject): array
    {
        $cursors = (array) $this->settings->get(SettingKey::KSEF_CURSORS);
        $overlap = $this->settings->int(SettingKey::KSEF_OVERLAP_MINUTES);
        $from = isset($cursors[$subject->value])
            ? (new \DateTimeImmutable((string) $cursors[$subject->value]))->modify(\sprintf('-%d minutes', $overlap))
            : ($this->settings->date(SettingKey::KSEF_SYNC_FROM) ?? new \DateTimeImmutable('first day of january this year'))->setTime(0, 0);

        $now = new \DateTimeImmutable();
        $new = [];

        while ($from < $now) {
            $windowEnd = $from->modify(\sprintf('+%d days', MetadataQuery::MAX_RANGE_DAYS));
            $isLast = $windowEnd >= $now;
            $query = new MetadataQuery($subject, $from, $isLast ? null : $windowEnd);
            $page = 0;

            while (true) {
                $result = $this->client->queryMetadata($query->withPage($page));
                foreach ($result->invoices as $metadata) {
                    $invoice = $this->importer->import($metadata, $subject);
                    if (null !== $invoice) {
                        $new[] = $invoice;
                    }
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
            $cursors[$subject->value] = $cursor->format(\DATE_ATOM);
            $this->settings->set(SettingKey::KSEF_CURSORS, $cursors, false);
            $this->em->flush();

            if ($isLast) {
                break;
            }
            $from = $windowEnd;
        }

        return $new;
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
            $parts[] = \sprintf('%d %s %s', $cost, FormatExtension::plural($cost, 'nowa', 'nowe', 'nowych'), FormatExtension::plural($cost, 'faktura kosztowa', 'faktury kosztowe', 'faktur kosztowych'));
        }
        if ($income > 0) {
            $parts[] = \sprintf('%d %s %s', $income, FormatExtension::plural($income, 'nowa', 'nowe', 'nowych'), FormatExtension::plural($income, 'faktura przychodowa', 'faktury przychodowe', 'faktur przychodowych'));
        }

        $this->notifier->notify(
            NotificationType::NewInvoices,
            NotificationPriority::Info,
            ucfirst(implode(', ', $parts)),
            null,
            $this->urls->generate('app_invoice_index', ['rodzaj' => $cost > 0 ? 'kosztowe' : 'przychodowe', 'status' => 'new']),
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
