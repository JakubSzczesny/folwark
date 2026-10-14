<?php

declare(strict_types=1);

namespace App\Ui;

use App\Entity\User;
use App\Security\Permission;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;
use Symfony\Component\Routing\Exception\RouteNotFoundException;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

final class Navigation
{
    /**
     * key, label, route, icon, permission, children, section-start.
     */
    private const array MENU = [
        ['key' => 'dashboard', 'label' => 'Pulpit', 'route' => 'app_dashboard', 'icon' => 'layout-dashboard', 'permission' => null],
        ['key' => 'invoices', 'label' => 'Faktury', 'route' => 'app_invoice_index', 'icon' => 'receipt-text', 'permission' => Permission::INVOICE_VIEW],
        ['key' => 'contractors', 'label' => 'Kontrahenci', 'route' => 'app_contractor_index', 'icon' => 'users', 'permission' => Permission::CONTRACTOR_VIEW, 'children' => [
            ['key' => 'contracts', 'label' => 'Umowy', 'route' => 'app_contract_index', 'permission' => Permission::CONTRACT_VIEW],
        ]],
        ['key' => 'orders', 'label' => 'Zamówienia', 'route' => 'app_order_index', 'icon' => 'shopping-cart', 'permission' => Permission::ORDER_VIEW],
        ['key' => 'machines', 'label' => 'Maszyny', 'route' => 'app_machine_index', 'icon' => 'tractor', 'permission' => Permission::MACHINE_VIEW],
        ['key' => 'warehouse', 'label' => 'Magazyn', 'route' => null, 'icon' => 'warehouse', 'permission' => null, 'children' => [
            ['key' => 'products', 'label' => 'Towary', 'route' => 'app_product_index', 'permission' => Permission::PRODUCT_VIEW],
            ['key' => 'documents', 'label' => 'Dokumenty', 'route' => 'app_document_index', 'permission' => Permission::DOCUMENT_VIEW],
            ['key' => 'stock', 'label' => 'Stany', 'route' => 'app_stock_index', 'permission' => Permission::DOCUMENT_VIEW],
        ]],
        ['key' => 'analytics', 'label' => 'Analizy', 'route' => 'app_analytics_index', 'icon' => 'chart-column', 'permission' => Permission::ANALYTICS_VIEW],
        ['key' => 'reports', 'label' => 'Zestawienia', 'route' => 'app_report_index', 'icon' => 'file-spreadsheet', 'permission' => Permission::EXPORT],
        ['key' => 'weighbridge', 'label' => 'Waga', 'route' => 'app_weighbridge', 'icon' => 'scale', 'permission' => null],
        ['key' => 'notifications', 'label' => 'Powiadomienia', 'route' => 'app_notification_index', 'icon' => 'bell', 'permission' => null],
        ['key' => 'settings', 'label' => 'Ustawienia', 'route' => 'app_settings', 'icon' => 'settings', 'permission' => Permission::SETTINGS, 'separator' => true],
    ];

    /** Skróty w dolnym pasku na telefonie; gdy rola nie ma dostępu do któregoś, wchodzą kolejne z listy. */
    private const array MOBILE = ['dashboard', 'invoices', 'machines', 'orders', 'contractors', 'products', 'weighbridge'];

    /** @var list<array<string, mixed>>|null */
    private ?array $cache = null;

    /**
     * @param iterable<MenuBadgeProvider> $badgeProviders
     */
    public function __construct(
        private readonly Security $security,
        private readonly UrlGeneratorInterface $urls,
        #[AutowireIterator(MenuBadgeProvider::class)]
        private readonly iterable $badgeProviders,
    ) {
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function items(): array
    {
        if (null !== $this->cache) {
            return $this->cache;
        }

        $user = $this->security->getUser();
        $badges = [];
        if ($user instanceof User) {
            foreach ($this->badgeProviders as $provider) {
                $badges += $provider->badges($user);
            }
        }

        return $this->cache = $this->build(self::MENU, $badges);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function mobileItems(): array
    {
        $byKey = [];
        foreach ($this->items() as $item) {
            $byKey[$item['key']] = $item;
            foreach ($item['children'] as $child) {
                $byKey[$child['key']] = ['icon' => $child['icon'] ?? $item['icon']] + $child;
            }
        }

        $result = [];
        foreach (self::MOBILE as $key) {
            if (isset($byKey[$key]) && \count($result) < 4) {
                $result[] = $byKey[$key];
            }
        }

        return $result;
    }

    /**
     * @param list<array<string, mixed>> $definitions
     * @param array<string, int>         $badges
     *
     * @return list<array<string, mixed>>
     */
    private function build(array $definitions, array $badges): array
    {
        $items = [];
        foreach ($definitions as $definition) {
            if (null !== $definition['permission'] && !$this->security->isGranted($definition['permission'])) {
                continue;
            }

            $children = $this->build($definition['children'] ?? [], $badges);
            $url = null !== $definition['route'] ? $this->url($definition['route']) : null;

            if (null === $url && [] === $children) {
                continue;
            }

            $items[] = [
                'key' => $definition['key'],
                'label' => $definition['label'],
                'icon' => $definition['icon'] ?? null,
                'route' => $definition['route'],
                'url' => $url ?? $children[0]['url'],
                'badge' => $badges[$definition['key']] ?? 0,
                'separator' => $definition['separator'] ?? false,
                'children' => $children,
            ];
        }

        return $items;
    }

    private function url(string $route): ?string
    {
        try {
            return $this->urls->generate($route);
        } catch (RouteNotFoundException) {
            return null;
        }
    }
}
