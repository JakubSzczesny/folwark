<?php

declare(strict_types=1);

namespace App\Calendar\Source;

use App\Calendar\CalendarEntry;
use App\Calendar\CalendarSource;
use App\Enum\OrderStatus;
use App\Repository\OrderCycleRepository;
use App\Security\Permission;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * Przewidywane terminy zamówień.
 */
final class OrderSource implements CalendarSource
{
    public function __construct(
        private readonly OrderCycleRepository $cycles,
        private readonly UrlGeneratorInterface $urls,
    ) {
    }

    public function label(): string
    {
        return 'Zamówienia';
    }

    public function permission(): string
    {
        return Permission::ORDER_VIEW;
    }

    public function entries(\DateTimeImmutable $from, \DateTimeImmutable $to): array
    {
        $entries = [];
        foreach ($this->cycles->findDue($to) as $cycle) {
            $date = $cycle->getPredictedDate();
            if (null === $date || $date < $from) {
                continue;
            }
            $entries[] = CalendarEntry::deadline(
                'Zamówić: '.$cycle->getLabel(),
                $date,
                $this->label(),
                $this->urls->generate('app_order_index', ['cykl' => $cycle->getId()]),
                OrderStatus::Overdue === $cycle->getStatus() ? 'po terminie' : null,
            );
        }

        return $entries;
    }
}
