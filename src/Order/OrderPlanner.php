<?php

declare(strict_types=1);

namespace App\Order;

use App\Entity\Contractor;
use App\Entity\OrderCycle;
use App\Entity\OrderSettings;
use App\Entity\Product;
use App\Entity\User;
use App\Enum\OrderStatus;
use App\Repository\ContractorRepository;
use App\Repository\OrderCycleRepository;
use App\Repository\OrderSettingsRepository;
use App\Repository\ProductRepository;
use App\Settings\SettingKey;
use App\Settings\Settings;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Cykle zamówień: przeliczenie po synchronizacji i codziennie, zamykanie cyklu przez nową fakturę,
 * ręczne oznaczenia („zamówione”, „pomiń”).
 */
final class OrderPlanner
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly OrderPredictor $predictor,
        private readonly PurchaseHistory $history,
        private readonly OrderCycleRepository $cycles,
        private readonly OrderSettingsRepository $settingsRepository,
        private readonly ProductRepository $products,
        private readonly ContractorRepository $contractors,
        private readonly Settings $settings,
    ) {
    }

    public function recalculate(?\DateTimeImmutable $today = null): void
    {
        $today = ($today ?? new \DateTimeImmutable('today'))->setTime(0, 0);
        $open = $this->cycles->findOpenIndexed();
        $manual = $this->settingsRepository->findIndexed();
        $seen = [];

        foreach ($this->history->byProduct() as $productId => $purchases) {
            $product = $this->products->find($productId);
            if (null === $product) {
                continue;
            }
            $key = 'p:'.$productId;
            $seen[$key] = true;
            $this->update($open[$key] ?? null, $product, $purchases, $manual[$key] ?? null, $product->getLeadTimeDays() ?? 0, $today);
        }

        foreach ($this->history->byContractor() as $contractorId => $purchases) {
            $contractor = $this->contractors->find($contractorId);
            if (null === $contractor) {
                continue;
            }
            $key = 'c:'.$contractorId;
            $seen[$key] = true;
            $this->update($open[$key] ?? null, $contractor, $purchases, $manual[$key] ?? null, $contractor->getDeliveryDays() ?? 0, $today);
        }

        foreach (array_diff_key($open, $seen) as $orphan) {
            $this->em->remove($orphan);
        }

        $this->em->flush();
    }

    public function prediction(Product|Contractor $subject, ?\DateTimeImmutable $today = null): Prediction
    {
        $purchases = $subject instanceof Product
            ? $this->history->byProduct($subject)[(int) $subject->getId()] ?? []
            : $this->history->byContractor($subject)[(int) $subject->getId()] ?? [];
        $manual = $this->settingsRepository->findOneBy([$subject instanceof Product ? 'product' : 'contractor' => $subject]);
        $delivery = $subject instanceof Product ? ($subject->getLeadTimeDays() ?? 0) : ($subject->getDeliveryDays() ?? 0);

        return $this->predictor->predict($purchases, $this->predictionSettings($manual, $delivery), ($today ?? new \DateTimeImmutable('today'))->setTime(0, 0));
    }

    public function mark(OrderCycle $cycle, OrderStatus $status, ?User $user): void
    {
        $cycle->mark($status, $user);
        $this->em->flush();
    }

    /**
     * Cofnięcie oznaczenia – status znów wynika z dat.
     */
    public function unmark(OrderCycle $cycle): void
    {
        $cycle->mark(OrderStatus::Expected, null);
        $cycle->refreshStatus(new \DateTimeImmutable('today'), 0);
        $this->em->flush();
    }

    public function settingsFor(Product|Contractor $subject): OrderSettings
    {
        $field = $subject instanceof Product ? 'product' : 'contractor';
        $settings = $this->settingsRepository->findOneBy([$field => $subject]);
        if (null === $settings) {
            $settings = $subject instanceof Product ? new OrderSettings($subject) : new OrderSettings(null, $subject);
            $this->em->persist($settings);
        }

        return $settings;
    }

    /**
     * @param list<Purchase> $purchases
     */
    private function update(?OrderCycle $cycle, Product|Contractor $subject, array $purchases, ?OrderSettings $manual, int $deliveryDays, \DateTimeImmutable $today): void
    {
        $prediction = $this->predictor->predict($purchases, $this->predictionSettings($manual, $deliveryDays), $today);
        $lastOrder = $prediction->lastOrder()?->date;

        if (null !== $cycle && null !== $lastOrder && null !== $cycle->getLastOrderDate() && $lastOrder > $cycle->getLastOrderDate()) {
            $cycle->close();
            $cycle = null;
        }
        if (null === $cycle) {
            $cycle = $subject instanceof Product ? new OrderCycle($subject) : new OrderCycle(null, $subject);
            $this->em->persist($cycle);
        }

        $cycle->apply($prediction);
        $cycle->refreshStatus($today, $this->settings->int(SettingKey::ORDERS_ORDERED_HOLD_DAYS));
    }

    private function predictionSettings(?OrderSettings $manual, int $deliveryDays): PredictionSettings
    {
        return new PredictionSettings(
            minOrders: $this->settings->int(SettingKey::ORDERS_MIN_ORDERS),
            mergeDays: $this->settings->int(SettingKey::ORDERS_MERGE_DAYS),
            regularThreshold: $this->settings->float(SettingKey::ORDERS_REGULAR_THRESHOLD),
            moderateThreshold: $this->settings->float(SettingKey::ORDERS_MODERATE_THRESHOLD),
            leadDays: $manual?->getLeadDays() ?? $this->settings->int(SettingKey::ORDERS_LEAD_DAYS),
            deliveryDays: $deliveryDays,
            manualIntervalDays: $manual?->getManualIntervalDays(),
            manualQuantity: null !== $manual?->getManualQuantity() ? (float) $manual->getManualQuantity() : null,
            seasonalDisabled: $manual?->isSeasonalDisabled() ?? false,
        );
    }
}
