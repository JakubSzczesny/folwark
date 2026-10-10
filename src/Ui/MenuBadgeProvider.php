<?php

declare(strict_types=1);

namespace App\Ui;

use App\Entity\User;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

/**
 * Dostarcza liczniki wyświetlane przy pozycjach menu.
 */
#[AutoconfigureTag]
interface MenuBadgeProvider
{
    /**
     * @return array<string, int> klucz pozycji menu => wartość licznika
     */
    public function badges(User $user): array;
}
