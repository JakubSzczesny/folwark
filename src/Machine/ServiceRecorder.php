<?php

declare(strict_types=1);

namespace App\Machine;

use App\Audit\AuditLogger;
use App\Entity\Contractor;
use App\Entity\Invoice;
use App\Entity\Machine;
use App\Entity\MachineService;
use App\Entity\MachineServiceAttachment;
use App\Entity\User;
use App\Enum\InvoiceKind;
use App\Repository\MachineServiceRepository;
use App\Storage\FileStorage;
use App\Storage\Thumbnails;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;

/**
 * Wpisy serwisowe: domyślne wartości z historii, zapis z aktualizacją licznika, podpowiedzi faktur.
 */
final class ServiceRecorder
{
    public const int INVOICE_WINDOW_DAYS = 14;

    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly MachineServiceRepository $services,
        private readonly FileStorage $storage,
        private readonly Thumbnails $thumbnails,
        private readonly AuditLogger $audit,
    ) {
    }

    /**
     * Nowy wpis z podpowiedziami: dzisiejsza data, bieżący licznik, wykonawca i rodzaj z ostatniego wpisu.
     */
    public function prepare(Machine $machine, ?User $user): MachineService
    {
        $service = (new MachineService($machine))
            ->setDate(new \DateTimeImmutable('today'))
            ->setMeterValue($machine->getMeterValue())
            ->setCreatedBy($user);

        $last = $this->services->findLastForMachine($machine);
        if (null !== $last) {
            $service->setType($last->getType())
                ->setContractor($last->getContractor())
                ->setPerformerName($last->getPerformerName());
        }

        return $service;
    }

    /**
     * @param list<UploadedFile> $files
     */
    public function save(MachineService $service, array $files = []): void
    {
        $isNew = null === $service->getId();
        $machine = $service->getMachine();
        if (null !== $service->getDate()) {
            $machine->raiseMeter($service->getMeterValue(), $service->getDate());
        }

        $this->em->persist($service);
        $this->em->flush();

        foreach ($files as $file) {
            $path = $this->storage->storeUpload($file, MachineManager::directory($machine).'/service/'.$service->getId());
            $service->addAttachment(new MachineServiceAttachment($service, $path, mb_substr($file->getClientOriginalName(), 0, 255), Thumbnails::isImage($file->getMimeType())));
        }

        $this->audit->log('MachineService', $service->getId(), $isNew ? 'create' : 'update', [
            'machine' => $machine->getName(),
            'type' => $service->getType()?->getName(),
            'date' => $service->getDate()?->format('Y-m-d'),
        ]);
        $this->em->flush();
    }

    public function delete(MachineService $service): void
    {
        foreach ($service->getAttachments() as $attachment) {
            $this->thumbnails->delete($attachment->getPath());
        }
        $this->audit->log('MachineService', $service->getId(), 'delete', ['machine' => $service->getMachine()->getName()]);
        $this->em->remove($service);
        $this->em->flush();
    }

    public function deleteAttachment(MachineServiceAttachment $attachment): void
    {
        $this->thumbnails->delete($attachment->getPath());
        $attachment->getService()->removeAttachment($attachment);
        $this->em->flush();
    }

    /**
     * Faktury kosztowe wykonawcy z ±14 dni od daty serwisu, najbliższe terminowo na górze.
     *
     * @return list<Invoice>
     */
    public function suggestInvoices(?Contractor $contractor, ?\DateTimeImmutable $date): array
    {
        if (null === $contractor || null === $date) {
            return [];
        }

        $window = \sprintf('%d days', self::INVOICE_WINDOW_DAYS);
        /** @var list<Invoice> $invoices */
        $invoices = $this->em->createQueryBuilder()
            ->select('i')
            ->from(Invoice::class, 'i')
            ->where('i.contractor = :contractor')
            ->andWhere('i.kind = :kind')
            ->andWhere('i.issueDate BETWEEN :from AND :to')
            ->setParameter('contractor', $contractor)
            ->setParameter('kind', InvoiceKind::Cost)
            ->setParameter('from', $date->modify('-'.$window))
            ->setParameter('to', $date->modify('+'.$window))
            ->getQuery()
            ->getResult();

        usort($invoices, static fn (Invoice $a, Invoice $b): int => abs($a->getIssueDate()->getTimestamp() - $date->getTimestamp()) <=> abs($b->getIssueDate()->getTimestamp() - $date->getTimestamp()));

        return $invoices;
    }
}
