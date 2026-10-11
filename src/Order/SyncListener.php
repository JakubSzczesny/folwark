<?php

declare(strict_types=1);

namespace App\Order;

use App\Farm\FarmContext;
use App\Invoice\Event\InvoiceXmlLoaded;
use App\Ksef\Event\KsefSyncCompleted;
use App\Message\RecalculateOrders;
use App\Product\ItemAssigner;
use App\Settings\SettingKey;
use App\Settings\Settings;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * Po pobraniu pozycji faktury: automatyczne przypisanie towarów z zapamiętanych mapowań.
 * Po synchronizacji i po każdym automatycznym przypisaniu: przeliczenie zamówień.
 */
final class SyncListener
{
    public function __construct(
        private readonly ItemAssigner $assigner,
        private readonly OrderPlanner $planner,
        private readonly Settings $settings,
        private readonly FarmContext $farmContext,
        private readonly MessageBusInterface $bus,
    ) {
    }

    #[AsEventListener]
    public function onXmlLoaded(InvoiceXmlLoaded $event): void
    {
        if (!$this->settings->bool(SettingKey::KSEF_AUTO_ASSIGN) || !$this->assigner->autoAssign($event->invoice)) {
            return;
        }

        $farmId = $this->farmContext->farmId();
        if (null !== $farmId) {
            $this->bus->dispatch(new RecalculateOrders($farmId));
        }
    }

    #[AsEventListener]
    public function onSyncCompleted(KsefSyncCompleted $event): void
    {
        $this->planner->recalculate();
    }
}
