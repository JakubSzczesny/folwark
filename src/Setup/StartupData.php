<?php

declare(strict_types=1);

namespace App\Setup;

use App\Entity\CostCategory;
use App\Entity\CostCenter;
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
