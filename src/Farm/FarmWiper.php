<?php

declare(strict_types=1);

namespace App\Farm;

use App\Entity\AuditLog;
use App\Entity\Contract;
use App\Entity\ContractAttachment;
use App\Entity\Contractor;
use App\Entity\CostCategory;
use App\Entity\CostCenter;
use App\Entity\Farm;
use App\Entity\Invoice;
use App\Entity\InvoiceRule;
use App\Entity\KsefSync;
use App\Entity\Notification;
use App\Entity\RecentChoice;
use App\Entity\SavedFilter;
use App\Entity\Setting;
use App\Entity\Unit;
use App\Entity\User;
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
     * Pozycje faktur, rachunki i historia kontrahentów usuwają się kaskadowo z rodzicami.
     *
     * @var list<class-string<FarmScoped>>
     */
    public const array WIPE_ORDER = [
        Notification::class,
        SavedFilter::class,
        RecentChoice::class,
        InvoiceRule::class,
        KsefSync::class,
        Invoice::class,
        ContractAttachment::class,
        Contract::class,
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
     * Usuwa wszystkie dane gospodarstwa (faktury, kontrahentów, umowy, słowniki, ustawienia, pliki)
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
