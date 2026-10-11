<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\Farm\FarmRunner;
use App\Message\RecalculateOrders;
use App\Order\OrderPlanner;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

final class OrderHandlers
{
    public function __construct(
        private readonly OrderPlanner $planner,
        private readonly FarmRunner $runner,
    ) {
    }

    #[AsMessageHandler]
    public function recalculate(RecalculateOrders $message): void
    {
        if (null === $message->farmId) {
            $this->runner->forEach(fn () => $this->planner->recalculate());

            return;
        }

        $farm = $this->runner->find($message->farmId);
        if (null !== $farm) {
            $this->runner->within($farm, fn () => $this->planner->recalculate());
        }
    }
}
