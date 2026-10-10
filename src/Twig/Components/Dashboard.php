<?php

declare(strict_types=1);

namespace App\Twig\Components;

use App\Dashboard\AttentionList;
use Symfony\UX\LiveComponent\Attribute\AsLiveComponent;
use Symfony\UX\LiveComponent\DefaultActionTrait;

#[AsLiveComponent]
final class Dashboard
{
    use DefaultActionTrait;

    public function __construct(
        private readonly AttentionList $attention,
    ) {
    }

    /**
     * @return array{items: list<\App\Dashboard\AttentionItem>, total: int}
     */
    public function getAttention(): array
    {
        return $this->attention->build();
    }

    public function getRefreshedAt(): \DateTimeImmutable
    {
        return new \DateTimeImmutable();
    }
}
