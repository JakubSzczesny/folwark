<?php

declare(strict_types=1);

namespace App\Scheduler;

use App\Message\GenerateDeadlineNotifications;
use App\Message\RecalculateOrders;
use App\Message\SendDailyDigest;
use App\Message\SyncKsefMetadata;
use Symfony\Component\Scheduler\Attribute\AsSchedule;
use Symfony\Component\Scheduler\RecurringMessage;
use Symfony\Component\Scheduler\Schedule;
use Symfony\Component\Scheduler\ScheduleProviderInterface;
use Symfony\Contracts\Cache\CacheInterface;

#[AsSchedule]
final class MainSchedule implements ScheduleProviderInterface
{
    private const string TIMEZONE = 'Europe/Warsaw';

    public function __construct(
        private readonly CacheInterface $cache,
    ) {
    }

    public function getSchedule(): Schedule
    {
        $tz = new \DateTimeZone(self::TIMEZONE);

        return (new Schedule())
            ->stateful($this->cache)
            ->processOnlyLastMissedRun(true)
            ->add(
                RecurringMessage::every('5 minutes', new SyncKsefMetadata()),
                RecurringMessage::cron('30 5 * * *', new RecalculateOrders(), $tz),
                RecurringMessage::cron('0 6 * * *', new GenerateDeadlineNotifications(), $tz),
                RecurringMessage::cron('5 6 * * *', new SendDailyDigest(), $tz),
            );
    }
}
