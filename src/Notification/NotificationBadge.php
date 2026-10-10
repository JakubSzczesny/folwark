<?php

declare(strict_types=1);

namespace App\Notification;

use App\Entity\User;
use App\Repository\NotificationRepository;
use App\Ui\MenuBadgeProvider;

final class NotificationBadge implements MenuBadgeProvider
{
    public function __construct(
        private readonly NotificationRepository $notifications,
    ) {
    }

    public function badges(User $user): array
    {
        return ['notifications' => $this->notifications->countUnread($user)];
    }
}
