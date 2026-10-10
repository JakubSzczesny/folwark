<?php

declare(strict_types=1);

namespace App\Twig\Components;

use App\Dashboard\AttentionList;
use App\Repository\WeighbridgeTaskRepository;
use Symfony\UX\LiveComponent\Attribute\AsLiveComponent;
use Symfony\UX\LiveComponent\DefaultActionTrait;

#[AsLiveComponent]
final class Dashboard
{
    use DefaultActionTrait;

    public function __construct(
        private readonly AttentionList $attention,
        private readonly WeighbridgeTaskRepository $weighbridgeTasks,
    ) {
    }

    /**
     * @return array{items: list<\App\Dashboard\AttentionItem>, total: int}
     */
    public function getAttention(): array
    {
        return $this->attention->build();
    }

    /**
     * @return array{done: int, total: int}
     */
    public function getWeighbridgeProgress(): array
    {
        return $this->weighbridgeTasks->progress();
    }

    public function getRefreshedAt(): \DateTimeImmutable
    {
        return new \DateTimeImmutable();
    }
}
