<?php

declare(strict_types=1);

namespace App\Notification;

use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

/**
 * Moduł, który codziennie o 6:00 sprawdza swoje terminy i tworzy powiadomienia.
 */
#[AutoconfigureTag]
interface DeadlineSource
{
    public function generate(\DateTimeImmutable $today): void;
}
