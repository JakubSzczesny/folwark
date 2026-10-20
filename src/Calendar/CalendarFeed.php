<?php

declare(strict_types=1);

namespace App\Calendar;

use App\Entity\CalendarEvent;
use App\Entity\User;
use App\Enum\CalendarCategory;
use App\Repository\CalendarEventRepository;
use App\Security\CalendarEventVoter;
use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;

/**
 * Pozycje kalendarza w zakresie dat: wydarzenia (rozwinięte powtórzenia) i terminy z modułów,
 * do których użytkownik ma dostęp.
 */
final class CalendarFeed
{
    /**
     * @param iterable<CalendarSource> $sources
     */
    public function __construct(
        private readonly CalendarEventRepository $events,
        #[AutowireIterator(CalendarSource::class)]
        private readonly iterable $sources,
        private readonly AuthorizationCheckerInterface $auth,
        private readonly UrlGeneratorInterface $urls,
    ) {
    }

    /**
     * @return list<CalendarEntry> posortowane wg dnia, całodniowe przed godzinowymi
     */
    public function entries(\DateTimeImmutable $from, \DateTimeImmutable $to, ?User $user, ?CalendarCategory $category = null, bool $onlyMine = false, bool $deadlines = true): array
    {
        $from = $from->setTime(0, 0);
        $to = $to->setTime(0, 0);
        $entries = [];

        foreach ($this->events->findForRange($from, $to, $user, $category, $onlyMine) as $event) {
            foreach (CalendarOccurrences::starts($event, $from, $to) as $start) {
                $entries[] = $this->entry($event, $start);
            }
        }

        if ($deadlines && null === $category && !$onlyMine) {
            foreach ($this->sources() as $source) {
                array_push($entries, ...$source->entries($from, $to));
            }
        }

        usort($entries, static fn (CalendarEntry $a, CalendarEntry $b): int => $a->sortKey() <=> $b->sortKey());

        return $entries;
    }

    /**
     * Pozycje pogrupowane wg dni (wielodniowe w każdym swoim dniu).
     *
     * @param list<CalendarEntry> $entries
     *
     * @return array<string, list<CalendarEntry>> „RRRR-MM-DD” => pozycje
     */
    public static function byDay(array $entries, \DateTimeImmutable $from, \DateTimeImmutable $to): array
    {
        $days = [];
        foreach ($entries as $entry) {
            $day = max($entry->start, $from->setTime(0, 0));
            $last = min($entry->end, $to->setTime(0, 0));
            for (; $day <= $last; $day = $day->modify('+1 day')) {
                $days[$day->format('Y-m-d')][] = $entry;
            }
        }

        return $days;
    }

    /**
     * Nazwy grup terminów z modułów widocznych dla użytkownika – do legendy.
     *
     * @return list<string>
     */
    public function sourceLabels(): array
    {
        return array_map(static fn (CalendarSource $source): string => $source->label(), $this->sources());
    }

    public function entry(CalendarEvent $event, \DateTimeImmutable $start): CalendarEntry
    {
        $detail = array_filter([$event->getLocation(), $event->getParticipants()->isEmpty() ? null : implode(', ', $event->getParticipants()->map(static fn (User $u): string => $u->getFullName())->toArray())]);

        return new CalendarEntry(
            $event->getTitle(),
            $start,
            $start->modify(\sprintf('+%d days', $event->getSpanDays())),
            $event->getCategory()->value,
            $event->getCategory()->label(),
            $this->urls->generate('app_calendar_event', ['id' => $event->getId(), 'dzien' => $start->format('Y-m-d')]),
            $event->getTimeLabel(),
            [] !== $detail ? implode(' · ', $detail) : null,
            $event->getId(),
            $event->isRecurring(),
            $event->isPrivate(),
            $this->auth->isGranted(CalendarEventVoter::EDIT, $event),
            $this->urls->generate('app_calendar_event', ['id' => $event->getId(), 'dzien' => $start->format('Y-m-d')]),
            $event->getStartTime()?->format('Hi'),
        );
    }

    /**
     * @return list<CalendarSource>
     */
    private function sources(): array
    {
        $allowed = [];
        foreach ($this->sources as $source) {
            if ($this->auth->isGranted($source->permission())) {
                $allowed[] = $source;
            }
        }

        return $allowed;
    }
}
