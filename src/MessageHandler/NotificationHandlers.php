<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\Message\GenerateDeadlineNotifications;
use App\Message\SendDailyDigest;
use App\Notification\DailyDigestMailer;
use App\Notification\DeadlineNotifications;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

final class NotificationHandlers
{
    public function __construct(
        private readonly DeadlineNotifications $deadlines,
        private readonly DailyDigestMailer $digest,
    ) {
    }

    #[AsMessageHandler]
    public function generate(GenerateDeadlineNotifications $message): void
    {
        $this->deadlines->generate();
    }

    #[AsMessageHandler]
    public function digest(SendDailyDigest $message): void
    {
        $this->digest->send();
    }
}
