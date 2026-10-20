<?php

declare(strict_types=1);

namespace App\Search;

use App\Entity\CalendarEvent;
use App\Entity\User;
use App\Repository\CalendarEventRepository;
use App\Security\Permission;
use App\Twig\FormatExtension;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

final class CalendarSearchProvider implements SearchProvider
{
    public function __construct(
        private readonly CalendarEventRepository $events,
        private readonly UrlGeneratorInterface $urls,
        private readonly Security $security,
    ) {
    }

    public function group(): string
    {
        return 'Kalendarz';
    }

    public function permission(): string
    {
        return Permission::CALENDAR_VIEW;
    }

    public function search(string $query, int $limit): array
    {
        $user = $this->security->getUser();

        return array_map(
            fn (CalendarEvent $event): SearchResult => new SearchResult(
                $event->getTitle(),
                $this->urls->generate('app_calendar_event', ['id' => $event->getId()]),
                implode(' · ', array_filter([
                    FormatExtension::date($event->getStartDate()).($event->isRecurring() ? ' ('.mb_strtolower($event->getRecurrence()->label()).')' : ''),
                    $event->getLocation(),
                ])),
            ),
            $this->events->search($query, $limit, $user instanceof User ? $user : null),
        );
    }
}
