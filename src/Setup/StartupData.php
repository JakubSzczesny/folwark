<?php

declare(strict_types=1);

namespace App\Setup;

use App\Entity\CostCategory;
use App\Entity\CostCenter;
use App\Entity\MachineCategory;
use App\Entity\MachineInfoCategory;
use App\Entity\ServiceType;
use App\Entity\Unit;
use App\Enum\CostCenterType;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Dane startowe bieżącego gospodarstwa. Każda grupa wstawiana jest tylko wtedy,
 * gdy w gospodarstwie jej jeszcze nie ma, więc można to uruchamiać wielokrotnie.
 */
final class StartupData
{
    private const array UNITS = [
        'kg' => 'kilogram',
        't' => 'tona',
        'l' => 'litr',
        'szt' => 'sztuka',
        'op' => 'opakowanie',
        'bela' => 'bela',
    ];

    private const array COST_CATEGORIES = [
        'Pasze', 'Paliwo', 'Nawozy', 'ŚOR', 'Nasiona', 'Części i serwis',
        'Energia', 'Usługi', 'Ubezpieczenia', 'Inne',
    ];

    private const array MACHINE_CATEGORIES = [
        'Ciągniki', 'Przyczepy i naczepy', 'Zestawy', 'Samochody', 'Kombajny', 'Maszyny uprawowe', 'Inne',
    ];

    /** Kategoria => podkategorie. */
    private const array MACHINE_INFO_CATEGORIES = [
        'Filtry' => ['Oleju silnika', 'Paliwa', 'Powietrza', 'Hydrauliki', 'Kabinowy'],
        'Oleje i płyny' => [],
        'Opony i koła' => [],
        'Części' => [],
        'Pasy i łańcuchy' => [],
        'Akumulatory' => [],
        'Dokumenty' => [],
    ];

    private const array SERVICE_TYPES = [
        'Przegląd okresowy', 'Naprawa', 'Wymiana oleju', 'Wymiana filtrów', 'Wymiana opon', 'Badanie techniczne', 'Inne',
    ];

    public function __construct(
        private readonly EntityManagerInterface $em,
    ) {
    }

    /**
     * @return list<string> nazwy uzupełnionych słowników
     */
    public function seed(): array
    {
        $seeded = [];
        if ($this->isEmpty(Unit::class)) {
            foreach (self::UNITS as $symbol => $name) {
                $this->em->persist(new Unit($symbol, $name));
            }
            $seeded[] = 'jednostki';
        }
        if ($this->isEmpty(CostCategory::class)) {
            foreach (self::COST_CATEGORIES as $name) {
                $this->em->persist(new CostCategory($name));
            }
            $seeded[] = 'kategorie kosztów';
        }
        if ($this->isEmpty(CostCenter::class)) {
            $this->em->persist((new CostCenter())->setType(CostCenterType::General)->setName('Ogólne gospodarstwa'));
            $this->em->persist((new CostCenter())->setType(CostCenterType::HerdGroup)->setName('Stado mleczne'));
            $seeded[] = 'ośrodki kosztów';
        }
        if ($this->isEmpty(MachineCategory::class)) {
            foreach (self::MACHINE_CATEGORIES as $position => $name) {
                $this->em->persist(new MachineCategory($name, $position));
            }
            $seeded[] = 'kategorie maszyn';
        }
        if ($this->isEmpty(MachineInfoCategory::class)) {
            $position = 0;
            foreach (self::MACHINE_INFO_CATEGORIES as $name => $children) {
                $this->em->persist($parent = new MachineInfoCategory($name, null, $position++));
                foreach ($children as $childPosition => $child) {
                    $this->em->persist(new MachineInfoCategory($child, $parent, $childPosition));
                }
            }
            $seeded[] = 'kategorie informacji o maszynach';
        }
        if ($this->isEmpty(ServiceType::class)) {
            foreach (self::SERVICE_TYPES as $position => $name) {
                $this->em->persist(new ServiceType($name, $position));
            }
            $seeded[] = 'rodzaje serwisu';
        }

        $this->em->flush();

        return $seeded;
    }

    /**
     * @param class-string $class
     */
    private function isEmpty(string $class): bool
    {
        return 0 === (int) $this->em->createQueryBuilder()
            ->select('COUNT(e)')
            ->from($class, 'e')
            ->getQuery()
            ->getSingleScalarResult();
    }
}
