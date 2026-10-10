<?php

declare(strict_types=1);

namespace App\Farm;

use App\Audit\AuditLogger;
use App\Entity\Farm;
use App\Entity\FarmMembership;
use App\Entity\User;
use App\Enum\UserRole;
use App\Setup\StartupData;
use App\Util\Nip;
use Doctrine\ORM\EntityManagerInterface;

final class FarmCreator
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly FarmRunner $runner,
        private readonly StartupData $startupData,
        private readonly AuditLogger $audit,
    ) {
    }

    /**
     * Zakłada gospodarstwo; twórca zostaje jego właścicielem.
     */
    public function create(string $name, ?string $nip, User $owner): Farm
    {
        $farm = (new Farm($name, $owner))->setNip(Nip::normalize($nip));
        $this->em->persist($farm);
        $this->em->persist(new FarmMembership($farm, $owner, UserRole::Owner));
        $this->em->flush();

        $this->runner->within($farm, function (Farm $farm) use ($owner): void {
            $this->startupData->seed();
            $this->audit->log('Farm', $farm->getId(), 'create', ['name' => $farm->getName()], $owner);
            $this->em->flush();
        });

        return $farm;
    }
}
