<?php

declare(strict_types=1);

namespace App\Dashboard;

use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

#[AutoconfigureTag]
interface AttentionSource
{
    public function permission(): ?string;

    /**
     * @return list<AttentionItem>
     */
    public function items(\DateTimeImmutable $today): array;
}
