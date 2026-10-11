<?php

declare(strict_types=1);

namespace App\Machine;

use App\Audit\AuditLogger;
use App\Entity\CostCenter;
use App\Entity\Machine;
use App\Entity\User;
use App\Enum\CostCenterType;
use App\Enum\TareSource;
use App\Storage\FileStorage;
use App\Storage\Thumbnails;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;

/**
 * Zapis i usuwanie maszyn: ośrodek kosztów maszyny, historia tary, zdjęcie.
 */
final class MachineManager
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly FileStorage $storage,
        private readonly Thumbnails $thumbnails,
        private readonly AuditLogger $audit,
    ) {
    }

    public static function directory(Machine $machine): string
    {
        $farm = $machine->getFarm() ?? throw new \LogicException('Maszyna bez gospodarstwa.');

        return FileStorage::farmDirectory($farm).'/machines/'.$machine->getId();
    }

    public function save(Machine $machine, ?int $tareKg, ?UploadedFile $photo, bool $removePhoto, ?User $user): void
    {
        $isNew = null === $machine->getId();
        $previousTare = $machine->getTareKg();

        $this->syncCostCenter($machine);
        if (null === $tareKg) {
            $machine->clearTare();
        } else {
            $machine->recordTare($tareKg, TareSource::Manual, $user);
        }

        $this->em->persist($machine);
        $this->em->flush();

        if ($removePhoto || null !== $photo) {
            $this->removePhoto($machine);
        }
        if (null !== $photo) {
            $machine->setPhotoPath($this->storage->storeUpload($photo, self::directory($machine).'/photo'));
        }

        $this->audit->log('Machine', $machine->getId(), $isNew ? 'create' : 'update', ['name' => $machine->getName()]);
        if ($previousTare !== $machine->getTareKg()) {
            $this->audit->log('Machine', $machine->getId(), 'tare', ['tareKg' => [$previousTare, $machine->getTareKg()]]);
        }
        $this->em->flush();
    }

    /**
     * Usuwa maszynę z wpisami i plikami. Ośrodek kosztów zostaje (wyłączony), żeby nie zgubić przypisanych faktur.
     */
    public function delete(Machine $machine): void
    {
        $directory = self::directory($machine);
        $id = $machine->getId();
        $machine->getCostCenter()?->setActive(false);

        $this->em->remove($machine);
        $this->audit->log('Machine', $id, 'delete', ['name' => $machine->getName()]);
        $this->em->flush();

        $this->storage->deleteDirectory($directory);
    }

    private function removePhoto(Machine $machine): void
    {
        if (null !== $machine->getPhotoPath()) {
            $this->thumbnails->delete($machine->getPhotoPath());
            $machine->setPhotoPath(null);
        }
    }

    private function syncCostCenter(Machine $machine): void
    {
        $center = $machine->getCostCenter();
        if (null === $center) {
            $center = (new CostCenter())->setType(CostCenterType::Machine);
            $this->em->persist($center);
            $machine->setCostCenter($center);
        }

        $center->setName(mb_substr($machine->getLabel(), 0, 120))->setActive($machine->isActive());
    }
}
