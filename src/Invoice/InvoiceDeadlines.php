<?php

declare(strict_types=1);

namespace App\Invoice;

use App\Dashboard\AttentionItem;
use App\Dashboard\AttentionSource;
use App\Entity\User;
use App\Enum\InvoiceKind;
use App\Enum\NotificationPriority;
use App\Enum\NotificationType;
use App\Notification\DeadlineSource;
use App\Notification\Notifier;
use App\Repository\InvoiceRepository;
use App\Repository\KsefSyncRepository;
use App\Security\Permission;
use App\Settings\SettingKey;
use App\Settings\Settings;
use App\Twig\FormatExtension;
use App\Ui\MenuBadgeProvider;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;

/**
 * Terminy płatności (powiadomienia o 6:00), nowe faktury i błędy KSeF na pulpicie, licznik nowych w menu.
 */
final class InvoiceDeadlines implements DeadlineSource, AttentionSource, MenuBadgeProvider
{
    public function __construct(
        private readonly InvoiceRepository $invoices,
        private readonly KsefSyncRepository $syncs,
        private readonly Notifier $notifier,
        private readonly Settings $settings,
        private readonly UrlGeneratorInterface $urls,
        private readonly AuthorizationCheckerInterface $auth,
    ) {
    }

    public function generate(\DateTimeImmutable $today): void
    {
        $before = $this->settings->int(SettingKey::NOTIFY_PAYMENT_DAYS);

        foreach ($this->invoices->findUnpaidDueBefore($today->modify(\sprintf('+%d days', $before))) as $invoice) {
            $days = FormatExtension::daysLeft($invoice->getPaymentDue(), $today) ?? 0;
            [$stage, $priority] = match (true) {
                $days < 0 => ['overdue', NotificationPriority::Urgent],
                0 === $days => ['due', NotificationPriority::Warning],
                default => ['before', NotificationPriority::Warning],
            };

            $this->notifier->notify(
                NotificationType::PaymentDue,
                $priority,
                \sprintf('Faktura %s – %s: %s', $invoice->getNumber(), $invoice->getContractorLabel(), self::dueLabel($days)),
                \sprintf('%s do zapłaty, termin %s', FormatExtension::money($invoice->getGross(), $invoice->getCurrency()), FormatExtension::date($invoice->getPaymentDue())),
                $this->urls->generate('app_invoice_show', ['id' => $invoice->getId()]),
                \sprintf('payment:%d:%s', $invoice->getId(), $stage),
            );
        }
    }

    public function permission(): string
    {
        return Permission::INVOICE_VIEW;
    }

    public function items(\DateTimeImmutable $today): array
    {
        $items = [];
        $canSeeAmounts = $this->auth->isGranted(Permission::AMOUNTS_VIEW);

        $new = $this->invoices->countNew();
        if ($new > 0) {
            $items[] = new AttentionItem(
                0,
                'new',
                'Faktury',
                \sprintf('%d %s', $new, FormatExtension::plural($new, 'nowa faktura', 'nowe faktury', 'nowych faktur')),
                $this->urls->generate('app_invoice_index', ['rodzaj' => 'wszystkie', 'status' => 'new']),
                'Do przejrzenia',
            );
        }

        $last = $this->syncs->findLastFinished();
        if (null !== $last && $last->isFailed()) {
            $items[] = new AttentionItem(-1000, 'danger', 'KSeF', 'Błąd synchronizacji KSeF', $this->urls->generate('app_settings_ksef'), $last->getError());
        }

        $before = $this->settings->int(SettingKey::NOTIFY_PAYMENT_DAYS);
        foreach ($this->invoices->findUnpaidDueBefore($today->modify(\sprintf('+%d days', $before))) as $invoice) {
            $days = FormatExtension::daysLeft($invoice->getPaymentDue(), $today) ?? 0;
            $items[] = new AttentionItem(
                $days,
                $days < 0 ? 'danger' : 'warning',
                'Płatność',
                \sprintf('%s – %s', $invoice->getNumber(), $invoice->getContractorLabel()),
                $this->urls->generate('app_invoice_show', ['id' => $invoice->getId()]),
                ucfirst(self::dueLabel($days)).($canSeeAmounts ? ' · '.FormatExtension::money($invoice->getGross(), $invoice->getCurrency()) : ''),
            );
        }

        return $items;
    }

    public static function dueLabel(int $days): string
    {
        return match (true) {
            $days < 0 => \sprintf('po terminie (%d %s)', -$days, FormatExtension::plural(-$days, 'dzień', 'dni', 'dni')),
            0 === $days => 'termin płatności dziś',
            default => 'termin płatności '.FormatExtension::daysLabel($days),
        };
    }

    public function badges(User $user): array
    {
        if (!$this->auth->isGranted(Permission::INVOICE_VIEW)) {
            return [];
        }

        return ['invoices' => $this->invoices->countNew(InvoiceKind::Cost)];
    }
}
