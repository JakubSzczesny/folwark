<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\Farm\FarmRunner;
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
        private readonly FarmRunner $runner,
    ) {
    }

    #[AsMessageHandler]
    public function generate(GenerateDeadlineNotifications $message): void
    {
        $this->runner->forEach(fn () => $this->deadlines->generate());
    }

    #[AsMessageHandler]
    public function digest(SendDailyDigest $message): void
    {
        $this->runner->forEach(fn () => $this->digest->send());
    }
}
