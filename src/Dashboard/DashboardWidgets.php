<?php

declare(strict_types=1);

namespace App\Dashboard;

use App\Entity\FarmMembership;
use App\Entity\User;
use App\Farm\FarmContext;
use App\Repository\FarmMembershipRepository;
use App\Security\Permission;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;

/**
 * Układ pulpitu użytkownika w gospodarstwie: które widżety widzi i w jakiej kolejności.
 * Domyślnie tylko „Wymaga uwagi”, pozostałe dodaje się samemu.
 */
final class DashboardWidgets
{
    public const array DEFAULT = ['attention'];

    /**
     * key => nazwa, opis, wymagane uprawnienie.
     */
    public const array CATALOG = [
        'attention' => ['label' => 'Wymaga uwagi', 'description' => 'Faktury, zamówienia, płatności i terminy do załatwienia.', 'permission' => null],
        'tiles' => ['label' => 'Kafelki', 'description' => 'Nowe faktury, zamówienia, koszty i przychody z wykresem 12 miesięcy.', 'permission' => null],
        'invoices' => ['label' => 'Ostatnie faktury', 'description' => '5 najnowszych faktur.', 'permission' => Permission::INVOICE_VIEW],
        'orders' => ['label' => 'Najbliższe zamówienia', 'description' => 'Co i kiedy trzeba będzie zamówić.', 'permission' => Permission::ORDER_VIEW],
        'calendar' => ['label' => 'Kalendarz', 'description' => 'Wydarzenia i terminy na najbliższe 2 tygodnie.', 'permission' => Permission::CALENDAR_VIEW],
        'chart' => ['label' => 'Koszty i przychody', 'description' => 'Wykres słupkowy z 12 miesięcy.', 'permission' => Permission::AMOUNTS_VIEW],
    ];

    public function __construct(
        private readonly Security $security,
        private readonly FarmContext $farmContext,
        private readonly FarmMembershipRepository $memberships,
        private readonly EntityManagerInterface $em,
    ) {
    }

    /**
     * Widżety na pulpicie, w kolejności.
     *
     * @return list<string>
     */
    public function active(): array
    {
        return array_values(array_filter(
            $this->membership()?->getDashboardWidgets() ?? self::DEFAULT,
            fn (string $key): bool => $this->allowed($key),
        ));
    }

    public function isActive(string $key): bool
    {
        return \in_array($key, $this->active(), true);
    }

    /**
     * Wszystkie widżety dostępne dla użytkownika, z informacją, czy są na pulpicie.
     *
     * @return list<array{key: string, label: string, description: string, active: bool}>
     */
    public function catalog(): array
    {
        $active = $this->active();
        $catalog = [];
        foreach (self::CATALOG as $key => $widget) {
            if ($this->allowed($key)) {
                $catalog[] = ['key' => $key, 'label' => $widget['label'], 'description' => $widget['description'], 'active' => \in_array($key, $active, true)];
            }
        }

        return $catalog;
    }

    public function add(string $key): void
    {
        if ($this->allowed($key) && !$this->isActive($key)) {
            $this->save([...$this->active(), $key]);
        }
    }

    public function remove(string $key): void
    {
        $this->save(array_values(array_diff($this->active(), [$key])));
    }

    /**
     * Przesuwa widżet o jedno miejsce w górę (-1) albo w dół (+1).
     */
    public function move(string $key, int $direction): void
    {
        $widgets = $this->active();
        $from = array_search($key, $widgets, true);
        $to = false !== $from ? $from + $direction : -1;
        if (false === $from || $to < 0 || $to >= \count($widgets)) {
            return;
        }
        [$widgets[$from], $widgets[$to]] = [$widgets[$to], $widgets[$from]];
        $this->save(array_values($widgets));
    }

    public function reset(): void
    {
        $this->save(null);
    }

    private function allowed(string $key): bool
    {
        if (!isset(self::CATALOG[$key])) {
            return false;
        }
        $permission = self::CATALOG[$key]['permission'];

        return null === $permission || $this->security->isGranted($permission);
    }

    /**
     * @param list<string>|null $widgets
     */
    private function save(?array $widgets): void
    {
        $membership = $this->membership() ?? throw new \LogicException('Brak członkostwa w gospodarstwie.');
        $membership->setDashboardWidgets($widgets);
        $this->em->flush();
    }

    private function membership(): ?FarmMembership
    {
        $user = $this->security->getUser();
        $farmId = $this->farmContext->farmId();
        if (!$user instanceof User || null === $farmId) {
            return null;
        }

        return $this->memberships->findOneBy(['user' => $user, 'farm' => $farmId]);
    }
}
