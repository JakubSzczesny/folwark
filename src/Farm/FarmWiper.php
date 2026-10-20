<?php

declare(strict_types=1);

namespace App\Farm;

use App\Entity\AuditLog;
use App\Entity\CalendarEvent;
use App\Entity\Contract;
use App\Entity\ContractAttachment;
use App\Entity\Contractor;
use App\Entity\CostCategory;
use App\Entity\CostCenter;
use App\Entity\CropSeason;
use App\Entity\Farm;
use App\Entity\Field;
use App\Entity\Invoice;
use App\Entity\InvoiceRule;
use App\Entity\ItemMapping;
use App\Entity\KsefSync;
use App\Entity\Machine;
use App\Entity\MachineCategory;
use App\Entity\MachineInfoAttachment;
use App\Entity\MachineInfoCategory;
use App\Entity\MachineInfoEntry;
use App\Entity\MachineService;
use App\Entity\MachineServiceAttachment;
use App\Entity\MilkProduction;
use App\Entity\Note;
use App\Entity\Notification;
use App\Entity\OrderCycle;
use App\Entity\OrderSettings;
use App\Entity\Product;
use App\Entity\ProductCategory;
use App\Entity\ProductLink;
use App\Entity\ProductLinkCategory;
use App\Entity\RecentChoice;
use App\Entity\SavedFilter;
use App\Entity\ServiceType;
use App\Entity\Setting;
use App\Entity\Stock;
use App\Entity\StockDocument;
use App\Entity\StockMovement;
use App\Entity\Unit;
use App\Entity\User;
use App\Entity\Warehouse;
use App\Setup\StartupData;
use App\Storage\FileStorage;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Czyszczenie danych gospodarstwa („format”) oraz usuwanie gospodarstwa.
 */
final class FarmWiper
{
    /**
     * Kolejność ma znaczenie: najpierw dane odwołujące się do słowników, na końcu słowniki.
     * Pozycje faktur, rachunki i historia kontrahentów, historia tary, części z serwisu
     * i uczestnicy wydarzeń usuwają się kaskadowo z rodzicami.
     *
     * @var list<class-string<FarmScoped>>
     */
    public const array WIPE_ORDER = [
        Notification::class,
        SavedFilter::class,
        RecentChoice::class,
        CalendarEvent::class,
        Note::class,
        InvoiceRule::class,
        KsefSync::class,
        OrderCycle::class,
        OrderSettings::class,
        ItemMapping::class,
        ProductLink::class,
        ProductLinkCategory::class,
        MachineServiceAttachment::class,
        MachineService::class,
        MachineInfoAttachment::class,
        MachineInfoEntry::class,
        MachineInfoCategory::class,
        Machine::class,
        MachineCategory::class,
        ServiceType::class,
        StockMovement::class,
        Stock::class,
        StockDocument::class,
        MilkProduction::class,
        CropSeason::class,
        Field::class,
        Warehouse::class,
        Invoice::class,
        ContractAttachment::class,
        Contract::class,
        Product::class,
        ProductCategory::class,
        Contractor::class,
        CostCenter::class,
        CostCategory::class,
        Unit::class,
        Setting::class,
        AuditLog::class,
    ];

    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly FarmRunner $runner,
        private readonly StartupData $startupData,
        private readonly FileStorage $storage,
    ) {
    }

    /**
     * Usuwa wszystkie dane gospodarstwa (faktury, kontrahentów, umowy, maszyny, słowniki, ustawienia, pliki)
     * i wstawia od nowa dane startowe. Gospodarstwo i jego członkowie zostają.
     */
    public function wipe(Farm $farm): void
    {
        $this->em->wrapInTransaction(function () use ($farm): void {
            $this->em->createQuery('UPDATE '.Invoice::class.' i SET i.correctedInvoice = NULL WHERE i.farm = :farm')
                ->setParameter('farm', $farm)
                ->execute();
            foreach (self::WIPE_ORDER as $class) {
                $this->em->createQuery(\sprintf('DELETE FROM %s e WHERE e.farm = :farm', $class))
                    ->setParameter('farm', $farm)
                    ->execute();
            }
        });

        $this->storage->deleteDirectory(FileStorage::farmDirectory($farm));
        $this->em->clear();

        $farm = $this->em->find(Farm::class, $farm->getId()) ?? throw new \LogicException('Gospodarstwo zniknęło w trakcie czyszczenia.');
        $this->runner->within($farm, fn () => $this->startupData->seed());
    }

    /**
     * Usuwa gospodarstwo razem ze wszystkimi danymi (kaskada w bazie) i plikami.
     */
    public function delete(Farm $farm): void
    {
        $directory = FileStorage::farmDirectory($farm);
        $this->em->createQuery('UPDATE '.User::class.' u SET u.lastFarm = NULL WHERE u.lastFarm = :farm')
            ->setParameter('farm', $farm)
            ->execute();
        $this->em->remove($farm);
        $this->em->flush();
        $this->storage->deleteDirectory($directory);
    }
}
