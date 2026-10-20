<?php

declare(strict_types=1);

namespace App\Module;

use App\Security\Permission;
use App\Settings\SettingKey;
use App\Settings\Settings;

/**
 * Moduły z menu, które właściciel może wyłączyć w gospodarstwie (Ustawienia → Moduły).
 * Wyłączenie tylko ukrywa moduł: dane zostają, a po ponownym włączeniu wszystko wraca.
 *
 * Wyłączony moduł: znika z menu i wyszukiwarki, jego ekrany są niedostępne, a jego uprawnienia
 * są odbierane wszystkim (PermissionVoter) – dzięki temu chowają się też widżety, linki i akcje
 * w innych modułach. Wyłączenie modułu nadrzędnego wyłącza też podrzędne.
 */
final class Modules
{
    /**
     * key => nazwa, opis, moduł nadrzędny, odbierane uprawnienia, prefiksy nazw tras.
     */
    public const array DEFINITIONS = [
        'invoices' => [
            'label' => 'Faktury',
            'description' => 'Faktury z KSeF, przypisywanie towarów, płatności i przelewy.',
            'permissions' => [Permission::INVOICE_VIEW, Permission::INVOICE_MANAGE],
            'routes' => ['app_invoice'],
        ],
        'contractors' => [
            'label' => 'Kontrahenci',
            'description' => 'Kartoteka kontrahentów i Biała lista VAT.',
            'permissions' => [Permission::CONTRACTOR_VIEW, Permission::CONTRACTOR_EDIT],
            'routes' => ['app_contractor'],
        ],
        'contracts' => [
            'label' => 'Umowy',
            'parent' => 'contractors',
            'description' => 'Umowy sprzedaży, zakupu i dzierżawy.',
            'permissions' => [Permission::CONTRACT_VIEW, Permission::CONTRACT_EDIT],
            'routes' => ['app_contract'],
        ],
        'orders' => [
            'label' => 'Zamówienia',
            'description' => 'Przewidywanie, co i kiedy zamówić.',
            'permissions' => [Permission::ORDER_VIEW, Permission::ORDER_MARK, Permission::ORDER_MANAGE],
            'routes' => ['app_order'],
        ],
        'machines' => [
            'label' => 'Maszyny',
            'description' => 'Park maszyn, informacje, serwis i terminy.',
            'permissions' => [Permission::MACHINE_VIEW, Permission::MACHINE_EDIT, Permission::MACHINE_MANAGE],
            'routes' => ['app_machine'],
        ],
        'calendar' => [
            'label' => 'Kalendarz',
            'description' => 'Wydarzenia gospodarstwa i terminy z innych modułów.',
            'permissions' => [Permission::CALENDAR_VIEW, Permission::CALENDAR_EDIT, Permission::CALENDAR_MANAGE],
            'routes' => ['app_calendar'],
        ],
        'warehouse' => [
            'label' => 'Magazyn',
            'description' => 'Cała grupa: towary, dokumenty i stany.',
            'permissions' => [],
            'routes' => [],
        ],
        'products' => [
            'label' => 'Towary',
            'parent' => 'warehouse',
            'description' => 'Kartoteka towarów. Przypisywanie pozycji faktur do towarów dalej działa.',
            'permissions' => [Permission::PRODUCT_VIEW, Permission::PRODUCT_EDIT],
            'routes' => ['app_product'],
        ],
        'documents' => [
            'label' => 'Dokumenty i stany',
            'parent' => 'warehouse',
            'description' => 'Dokumenty PZ/PW/WZ/RW/MM/INW i stany magazynowe.',
            'permissions' => [Permission::DOCUMENT_VIEW, Permission::DOCUMENT_EDIT],
            'routes' => ['app_document', 'app_stock'],
        ],
        'analytics' => [
            'label' => 'Analizy',
            'description' => 'Koszty, ceny, ośrodki, zużycie i przychody.',
            'permissions' => [Permission::ANALYTICS_VIEW],
            'routes' => ['app_analytics'],
        ],
        'reports' => [
            'label' => 'Zestawienia',
            'description' => 'Zestawienia XLSX/PDF. Eksport z list (np. faktur) zostaje.',
            'permissions' => [],
            'routes' => ['app_report'],
        ],
        'weighbridge' => [
            'label' => 'Waga',
            'description' => 'Lista do zrobienia przy wadze.',
            'permissions' => [],
            'routes' => ['app_weighbridge'],
        ],
        'notifications' => [
            'label' => 'Powiadomienia',
            'description' => 'Dzwonek, lista powiadomień i dzienny skrót e-mail. Wyłączone – nowe nie powstają.',
            'permissions' => [],
            'routes' => ['app_notification'],
        ],
    ];

    public function __construct(
        private readonly Settings $settings,
    ) {
    }

    public static function exists(string $key): bool
    {
        return isset(self::DEFINITIONS[$key]);
    }

    public function isEnabled(string $key): bool
    {
        if (!self::exists($key)) {
            return true;
        }

        $disabled = $this->disabledKeys();
        for ($current = $key; null !== $current; $current = self::DEFINITIONS[$current]['parent'] ?? null) {
            if (\in_array($current, $disabled, true)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Moduły wyłączone wprost przez użytkownika (bez podrzędnych wyłączonych przez rodzica).
     *
     * @return list<string>
     */
    public function disabledKeys(): array
    {
        $value = $this->settings->get(SettingKey::MODULES_DISABLED);

        return \is_array($value) ? array_values(array_filter($value, static fn (mixed $key): bool => \is_string($key) && self::exists($key))) : [];
    }

    /**
     * @param list<string> $keys
     */
    public function setDisabled(array $keys): void
    {
        $keys = array_values(array_unique(array_filter($keys, self::exists(...))));
        sort($keys);
        $this->settings->set(SettingKey::MODULES_DISABLED, $keys);
    }

    public function enable(string $key): void
    {
        $this->setDisabled(array_values(array_diff($this->disabledKeys(), [$key])));
    }

    public function disable(string $key): void
    {
        $this->setDisabled([...$this->disabledKeys(), $key]);
    }

    /**
     * Moduł, do którego należy uprawnienie, jeśli jest wyłączony.
     */
    public function isPermissionDisabled(string $permission): bool
    {
        foreach (self::DEFINITIONS as $key => $module) {
            if (\in_array($permission, $module['permissions'], true)) {
                return !$this->isEnabled($key);
            }
        }

        return false;
    }

    /**
     * Wyłączony moduł, do którego należy trasa (albo null).
     */
    public function disabledModuleOfRoute(string $route): ?string
    {
        foreach (self::DEFINITIONS as $key => $module) {
            foreach ($module['routes'] as $prefix) {
                if (($route === $prefix || str_starts_with($route, $prefix.'_')) && !$this->isEnabled($key)) {
                    return $key;
                }
            }
        }

        return null;
    }

    public static function label(string $key): string
    {
        return self::DEFINITIONS[$key]['label'] ?? $key;
    }

    /**
     * Lista do ekranu ustawień: moduły nadrzędne, a pod każdym jego podrzędne.
     *
     * @return list<array{key: string, label: string, description: string, child: bool, enabled: bool, parentEnabled: bool}>
     */
    public function overview(): array
    {
        $disabled = $this->disabledKeys();
        $list = [];
        foreach (self::DEFINITIONS as $key => $module) {
            if (isset($module['parent'])) {
                continue;
            }
            $list[] = ['key' => $key, 'label' => $module['label'], 'description' => $module['description'], 'child' => false, 'enabled' => !\in_array($key, $disabled, true), 'parentEnabled' => true];
            foreach (self::DEFINITIONS as $childKey => $child) {
                if (($child['parent'] ?? null) === $key) {
                    $list[] = ['key' => $childKey, 'label' => $child['label'], 'description' => $child['description'], 'child' => true, 'enabled' => !\in_array($childKey, $disabled, true), 'parentEnabled' => !\in_array($key, $disabled, true)];
                }
            }
        }

        return $list;
    }
}
