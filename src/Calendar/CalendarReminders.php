<?php

declare(strict_types=1);

namespace App\Calendar;

use App\Entity\CalendarEvent;
use App\Entity\User;
use App\Enum\NotificationPriority;
use App\Enum\NotificationType;
use App\Notification\DeadlineSource;
use App\Notification\Notifier;
use App\Repository\CalendarEventRepository;
use App\Twig\FormatExtension;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * Przypomnienia o wydarzeniach (codziennie o 6:00) i powiadomienie osób dopisanych do wydarzenia.
 * Odbiorcy: uczestnicy; bez uczestników – autor prywatnego albo wszyscy członkowie gospodarstwa.
 */
final class CalendarReminders implements DeadlineSource
{
    public function __construct(
        private readonly CalendarEventRepository $events,
        private readonly Notifier $notifier,
        private readonly UrlGeneratorInterface $urls,
    ) {
    }

    public function generate(\DateTimeImmutable $today): void
    {
        $today = $today->setTime(0, 0);
        foreach ($this->events->findWithReminder($today) as $event) {
            $days = (int) $event->getReminderDays();
            foreach (CalendarOccurrences::starts($event, $today, $today->modify(\sprintf('+%d days', $days))) as $start) {
                if ($start < $today) {
                    continue;
                }
                $left = (int) $today->diff($start)->days;
                $this->notifier->notify(
                    NotificationType::CalendarEvent,
                    0 === $left ? NotificationPriority::Warning : NotificationPriority::Info,
                    \sprintf('%s – %s', $event->getTitle(), self::when($start, $left, $event)),
                    $event->getLocation(),
                    $this->url($event, $start),
                    \sprintf('calendar:%d:%s', $event->getId(), $start->format('Ymd')),
                    $this->recipients($event),
                );
            }
        }
    }

    /**
     * Powiadamia nowo dopisanych uczestników (poza osobą, która ich dopisała).
     *
     * @param list<User> $added
     */
    public function notifyAdded(CalendarEvent $event, array $added, ?User $by): void
    {
        $recipients = array_values(array_filter($added, static fn (User $user): bool => $user->getId() !== $by?->getId()));
        $start = $event->getStartDate();
        if ([] === $recipients || null === $start || $event->isPrivate()) {
            return;
        }

        $this->notifier->notify(
            NotificationType::CalendarEvent,
            NotificationPriority::Info,
            \sprintf('Dodano Cię do wydarzenia: %s', $event->getTitle()),
            trim(FormatExtension::date($start).' '.($event->getTimeLabel() ?? '')).($by ? ' · dodał(a) '.$by->getFullName() : ''),
            $this->url($event, $start),
            \sprintf('calendar-added:%d:%s', $event->getId(), $start->format('Ymd')),
            $recipients,
        );
    }

    /**
     * @return list<User>|null null = domyślni odbiorcy typu (wszyscy członkowie)
     */
    private function recipients(CalendarEvent $event): ?array
    {
        if (!$event->getParticipants()->isEmpty()) {
            return array_values($event->getParticipants()->toArray());
        }
        if ($event->isPrivate()) {
            return null !== $event->getCreatedBy() ? [$event->getCreatedBy()] : [];
        }

        return null;
    }

    private function url(CalendarEvent $event, \DateTimeImmutable $start): string
    {
        return $this->urls->generate('app_calendar_event', ['id' => $event->getId(), 'dzien' => $start->format('Y-m-d')]);
    }

    private static function when(\DateTimeImmutable $start, int $left, CalendarEvent $event): string
    {
        $label = match ($left) {
            0 => 'dziś',
            1 => 'jutro',
            default => FormatExtension::date($start),
        };

        return null !== $event->getTimeLabel() ? $label.', '.$event->getTimeLabel() : $label;
    }
}
