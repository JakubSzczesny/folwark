<?php

declare(strict_types=1);

namespace App\Setup;

use App\Entity\CostCategory;
use App\Entity\CostCenter;
use App\Entity\Unit;
use App\Entity\WeighbridgeTask;
use App\Enum\CostCenterType;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Dane startowe ze specyfikacji. Każda grupa wstawiana jest tylko wtedy, gdy tabela jest pusta,
 * więc polecenie można uruchamiać wielokrotnie.
 */
final class StartupData
{
    private const array WEIGHBRIDGE_TASKS = [
        'Terminal wagi (RHEWA 82c)' => [
            'Sprawdzić złącza danych terminala (RS232 / RS485 / Ethernet / USB): tył obudowy, tabliczka, menu konfiguracji.',
            'Ustalić, czym jest urządzenie pod terminalem i do czego jest podłączone.',
            'Uzyskać od serwisu wagi (kontakt na naklejce na terminalu) dokumentację protokołu transmisji i ustawień interfejsu.',
            'Ustalić tryb wysyłki (ciągły / na zapytanie), format ramki, prędkość, bity danych, parzystość.',
            'Ustalić, czy ramka zawiera flagę stabilności, znak, jednostkę i numer ważenia z pamięci.',
            'Uzgodnić z serwisem, że podłączenie nie narusza legalizacji (plomby).',
            'Nagrać próbkę transmisji (~10 min: pusta waga, wjazd, postój, zjazd) do pliku – do symulatora i testów.',
        ],
        'Kamery' => [
            'Wybrać miejsce montażu: widok ładunku z góry i tablicy rejestracyjnej; oświetlenie nocne.',
            'Ustalić model kamer, zasilanie (PoE), stały adres IP, dane logowania.',
            'Sprawdzić w przeglądarce adres zdjęcia (snapshot HTTP) lub strumienia RTSP.',
            'Zmierzyć czas pobrania zdjęcia (cel < 2 s).',
        ],
        'Infrastruktura' => [
            'Zdecydować, gdzie działa nasłuch wagi (serwer czy mini PC przy wadze); połączenie kablowe; UPS.',
        ],
        'Dane i parametry' => [
            'Uzupełnić tary maszyn w module Maszyny.',
            'Potwierdzić parametry automatu: próg 400 kg, tolerancja ± 20 kg, czas stabilności 10 s, zmiana masy dla nowego pomiaru 100 kg.',
        ],
    ];

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
        if ($this->isEmpty(WeighbridgeTask::class)) {
            $this->seedWeighbridgeTasks();
            $seeded[] = 'lista do zrobienia wagi';
        }

        $this->em->flush();

        return $seeded;
    }

    private function seedWeighbridgeTasks(): void
    {
        $position = 0;
        foreach (self::WEIGHBRIDGE_TASKS as $group => $tasks) {
            foreach ($tasks as $content) {
                $this->em->persist((new WeighbridgeTask())->setGroupName($group)->setContent($content)->setPosition(++$position));
            }
        }
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
