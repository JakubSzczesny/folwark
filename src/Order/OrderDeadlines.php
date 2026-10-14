<?php

declare(strict_types=1);

namespace App\Order;

use App\Dashboard\AttentionAction;
use App\Dashboard\AttentionItem;
use App\Dashboard\AttentionSource;
use App\Entity\OrderCycle;
use App\Entity\User;
use App\Enum\NotificationPriority;
use App\Enum\NotificationType;
use App\Enum\OrderMode;
use App\Enum\OrderStatus;
use App\Notification\DeadlineSource;
use App\Notification\Notifier;
use App\Repository\OrderCycleRepository;
use App\Security\Permission;
use App\Twig\FormatExtension;
use App\Ui\MenuBadgeProvider;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;

/**
 * Przypomnienia o zamówieniach (w dniu przypomnienia i po terminie), pozycje na pulpicie, licznik w menu.
 */
final class OrderDeadlines implements DeadlineSource, AttentionSource, MenuBadgeProvider
{
    public const int BADGE_DAYS = 14;

    public function __construct(
        private readonly OrderCycleRepository $cycles,
        private readonly Notifier $notifier,
        private readonly UrlGeneratorInterface $urls,
        private readonly AuthorizationCheckerInterface $auth,
    ) {
    }

    public function generate(\DateTimeImmutable $today): void
    {
        foreach ($this->cycles->findDue($today->modify('+60 days')) as $cycle) {
            if (!self::notifies($cycle)) {
                continue;
            }

            if (OrderStatus::Overdue === $cycle->getStatus()) {
                $this->notifier->notify(
                    NotificationType::OrderOverdue,
                    NotificationPriority::Warning,
                    \sprintf('%s: zamówienie po terminie', $cycle->getLabel()),
                    \sprintf('Przewidywane na %s, a faktury wciąż nie ma.', FormatExtension::date($cycle->getPredictedDate())),
                    $this->url($cycle),
                    \sprintf('order:%d:overdue', $cycle->getId()),
                );
            } elseif (OrderStatus::Approaching === $cycle->getStatus()) {
                $this->notifier->notify(
                    NotificationType::OrderApproaching,
                    NotificationPriority::Info,
                    \sprintf('Czas zamówić: %s', $cycle->getLabel()),
                    self::detail($cycle),
                    $this->url($cycle),
                    \sprintf('order:%d:approaching', $cycle->getId()),
                );
            }
        }
    }

    public function permission(): string
    {
        return Permission::ORDER_VIEW;
    }

    public function items(\DateTimeImmutable $today): array
    {
        $items = [];
        foreach ($this->cycles->findDue($today) as $cycle) {
            if (!\in_array($cycle->getStatus(), [OrderStatus::Overdue, OrderStatus::Approaching], true)) {
                continue;
            }
            $days = FormatExtension::daysLeft($cycle->getPredictedDate(), $today) ?? 0;
            $overdue = OrderStatus::Overdue === $cycle->getStatus();
            $items[] = new AttentionItem(
                $overdue ? min($days, -1) : $days,
                $overdue ? 'danger' : 'warning',
                'Zamówienie',
                $cycle->getLabel(),
                $this->url($cycle),
                $overdue ? 'Po terminie – brak faktury' : self::detail($cycle),
                $this->actions($cycle),
            );
        }

        return $items;
    }

    /**
     * @return list<AttentionAction>
     */
    private function actions(OrderCycle $cycle): array
    {
        $actions = [];
        if ($this->auth->isGranted(Permission::ORDER_MARK)) {
            $actions[] = AttentionAction::post('Oznacz jako zamówione', $this->urls->generate('app_order_mark', ['id' => $cycle->getId()]), 'order', 'shopping-cart');
        }
        if ($this->auth->isGranted(Permission::ORDER_MANAGE)) {
            $actions[] = AttentionAction::post('Pomiń ten termin', $this->urls->generate('app_order_skip', ['id' => $cycle->getId()]), 'order');
        }

        return $actions;
    }

    public function badges(User $user): array
    {
        if (!$this->auth->isGranted(Permission::ORDER_VIEW)) {
            return [];
        }

        return ['orders' => \count($this->cycles->findDue(new \DateTimeImmutable(\sprintf('today +%d days', self::BADGE_DAYS))))];
    }

    private static function notifies(OrderCycle $cycle): bool
    {
        return OrderMode::Manual === $cycle->getMode() || true === $cycle->getRegularity()?->notifies();
    }

    private static function detail(OrderCycle $cycle): string
    {
        $parts = ['przewidywane '.FormatExtension::date($cycle->getPredictedDate())];
        if (null !== $cycle->getTypicalQuantity()) {
            $parts[] = 'zwykle '.FormatExtension::quantity($cycle->getTypicalQuantity()).' '.$cycle->getProduct()?->getUnitSymbol();
        }

        return ucfirst(implode(' · ', $parts));
    }

    private function url(OrderCycle $cycle): string
    {
        return $this->urls->generate('app_order_index', ['cykl' => $cycle->getId()]);
    }
}
