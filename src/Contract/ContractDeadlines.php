<?php

declare(strict_types=1);

namespace App\Contract;

use App\Dashboard\AttentionItem;
use App\Dashboard\AttentionSource;
use App\Enum\NotificationPriority;
use App\Enum\NotificationType;
use App\Notification\DeadlineSource;
use App\Notification\Notifier;
use App\Repository\ContractRepository;
use App\Security\Permission;
use App\Settings\SettingKey;
use App\Settings\Settings;
use App\Twig\FormatExtension;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

final class ContractDeadlines implements DeadlineSource, AttentionSource
{
    private const int LEASE_PAYMENT_DAYS = 14;

    public function __construct(
        private readonly ContractRepository $contracts,
        private readonly Notifier $notifier,
        private readonly Settings $settings,
        private readonly UrlGeneratorInterface $urls,
    ) {
    }

    public function generate(\DateTimeImmutable $today): void
    {
        foreach ($this->contracts->findExpiring($today, $this->settings->int(SettingKey::NOTIFY_CONTRACT_DAYS)) as $contract) {
            $validTo = $contract->getValidTo();
            \assert(null !== $validTo);

            $this->notifier->notify(
                NotificationType::ContractExpiring,
                NotificationPriority::Warning,
                \sprintf('Umowa %s (%s) wygasa %s', $contract->getNumber(), $contract->getContractor()?->getDisplayName(), FormatExtension::date($validTo)),
                $contract->getSubject(),
                $this->urls->generate('app_contract_show', ['id' => $contract->getId()]),
                \sprintf('contract:%d:%s', $contract->getId(), $validTo->format('Ymd')),
            );
        }
    }

    public function permission(): string
    {
        return Permission::CONTRACT_VIEW;
    }

    public function items(\DateTimeImmutable $today): array
    {
        $items = [];
        foreach ($this->contracts->findExpiring($today, $this->settings->int(SettingKey::ATTENTION_DAYS)) as $contract) {
            $days = FormatExtension::daysLeft($contract->getValidTo(), $today) ?? 0;
            $items[] = new AttentionItem(
                $days,
                $days <= 7 ? 'warning' : 'neutral',
                'Umowa',
                \sprintf('%s – %s', $contract->getNumber(), $contract->getContractor()?->getDisplayName()),
                $this->urls->generate('app_contract_show', ['id' => $contract->getId()]),
                'Wygasa '.FormatExtension::daysLabel($days),
            );
        }

        foreach ($this->contracts->findActiveLeases() as $contract) {
            $next = $contract->nextPaymentDate($today);
            $days = FormatExtension::daysLeft($next, $today);
            if (null === $days || $days > self::LEASE_PAYMENT_DAYS) {
                continue;
            }
            $items[] = new AttentionItem(
                $days,
                $days <= 3 ? 'warning' : 'neutral',
                'Dzierżawa',
                \sprintf('%s – %s', $contract->getNumber(), $contract->getContractor()?->getDisplayName()),
                $this->urls->generate('app_contract_show', ['id' => $contract->getId()]),
                \sprintf('Płatność %s%s', FormatExtension::daysLabel($days), null !== $contract->getRentAmount() ? ' · '.FormatExtension::money($contract->getRentAmount()) : ''),
            );
        }

        return $items;
    }
}
