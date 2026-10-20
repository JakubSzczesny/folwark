<?php

declare(strict_types=1);

namespace App\Calendar\Source;

use App\Calendar\CalendarEntry;
use App\Calendar\CalendarSource;
use App\Entity\Machine;
use App\Repository\MachineRepository;
use App\Repository\MachineServiceRepository;
use App\Security\Permission;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * Końce ubezpieczeń, przeglądy techniczne i zaplanowane serwisy maszyn.
 */
final class MachineSource implements CalendarSource
{
    private const array DEADLINES = [
        'insuranceUntil' => 'Koniec ubezpieczenia',
        'inspectionUntil' => 'Przegląd techniczny',
    ];

    public function __construct(
        private readonly MachineRepository $machines,
        private readonly MachineServiceRepository $services,
        private readonly UrlGeneratorInterface $urls,
    ) {
    }

    public function label(): string
    {
        return 'Maszyny';
    }

    public function permission(): string
    {
        return Permission::MACHINE_VIEW;
    }

    public function entries(\DateTimeImmutable $from, \DateTimeImmutable $to): array
    {
        $entries = [];
        foreach (self::DEADLINES as $field => $title) {
            /** @var list<Machine> $machines */
            $machines = $this->machines->createQueryBuilder('m')
                ->where('m.active = true')
                ->andWhere(\sprintf('m.%s BETWEEN :from AND :to', $field))
                ->setParameter('from', $from->format('Y-m-d'))
                ->setParameter('to', $to->format('Y-m-d'))
                ->getQuery()
                ->getResult();
            foreach ($machines as $machine) {
                $date = 'insuranceUntil' === $field ? $machine->getInsuranceUntil() : $machine->getInspectionUntil();
                \assert(null !== $date);
                $entries[] = CalendarEntry::deadline($title.': '.$machine->getName(), $date, $this->label(), $this->urls->generate('app_machine_show', ['id' => $machine->getId()]));
            }
        }

        foreach ($this->services->findOpenPlans(null, $to) as $plan) {
            $date = $plan->getNextServiceDate();
            if (null === $date || $date < $from) {
                continue;
            }
            $machine = $plan->getMachine();
            $entries[] = CalendarEntry::deadline('Serwis: '.$machine->getName(), $date, $this->label(), $this->urls->generate('app_machine_service', ['id' => $machine->getId()]), $plan->getType()?->getName());
        }

        return $entries;
    }
}
