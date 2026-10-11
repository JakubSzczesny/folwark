<?php

declare(strict_types=1);

namespace App\Tests\Unit\Machine;

use App\Entity\Machine;
use App\Enum\TareSource;
use App\Machine\MachineDeadlines;
use App\Machine\MachineInfo;
use PHPUnit\Framework\TestCase;

final class MachineRulesTest extends TestCase
{
    public function testLinksAreParsedWithOptionalDescription(): void
    {
        self::assertSame([
            ['https://sklep.example.pl/filtr-re504836', 'Sklep Agrihandel'],
            ['https://www.deere.pl/czesci', null],
        ], MachineInfo::parseLinks("Sklep Agrihandel – https://sklep.example.pl/filtr-re504836\n\nto nie jest link\nhttps://www.deere.pl/czesci."));
    }

    public function testReminderStagePicksSmallestMatchingThreshold(): void
    {
        $thresholds = [30, 14, 3];

        self::assertSame('early', MachineDeadlines::stage(45, $thresholds));
        self::assertSame('d30', MachineDeadlines::stage(20, $thresholds));
        self::assertSame('d14', MachineDeadlines::stage(14, $thresholds));
        self::assertSame('d3', MachineDeadlines::stage(0, $thresholds));
        self::assertSame('overdue', MachineDeadlines::stage(-1, $thresholds));
    }

    public function testServiceOnlyRaisesMeter(): void
    {
        $machine = (new Machine())->setMeterValue('1500.0');

        self::assertFalse($machine->raiseMeter('1400.0', new \DateTimeImmutable('2026-09-01')));
        self::assertSame('1500.0', $machine->getMeterValue());
        self::assertTrue($machine->raiseMeter('1620.5', new \DateTimeImmutable('2026-10-01')));
        self::assertSame('1620.5', $machine->getMeterValue());
        self::assertSame('2026-10-01', $machine->getMeterUpdatedAt()?->format('Y-m-d'));
    }

    public function testTareChangeGoesToHistoryOnlyWhenDifferent(): void
    {
        $machine = new Machine();

        self::assertNotNull($machine->recordTare(6850, TareSource::Manual, null));
        self::assertNull($machine->recordTare(6850, TareSource::Manual, null));
        self::assertNotNull($machine->recordTare(6900, TareSource::Measured, null));
        self::assertCount(2, $machine->getTareHistory());
        self::assertSame(TareSource::Measured, $machine->getTareSource());
    }

    public function testSetTareIsSumOfComponents(): void
    {
        $tractor = new Machine();
        $tractor->recordTare(6850, TareSource::Manual, null);
        $trailer = new Machine();
        $trailer->recordTare(3900, TareSource::Manual, null);
        $set = (new Machine())->addComponent($tractor)->addComponent($trailer);

        self::assertSame(10750, $set->componentsTareKg());
        $set->addComponent(new Machine());
        self::assertNull($set->componentsTareKg(), 'Bez tary któregoś składnika suma nie ma sensu');
    }
}
