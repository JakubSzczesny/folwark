<?php

declare(strict_types=1);

namespace App\Farm;

use App\Entity\AuditLog;
use App\Entity\Farm;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsDoctrineListener;
use Doctrine\ORM\Event\PrePersistEventArgs;
use Doctrine\ORM\Events;

/**
 * Nowe encje gospodarstwa dostają bieżące gospodarstwo, jeśli nie ustawiono go jawnie.
 */
#[AsDoctrineListener(event: Events::prePersist)]
final class FarmAssignListener
{
    public function __construct(
        private readonly FarmContext $context,
    ) {
    }

    public function prePersist(PrePersistEventArgs $args): void
    {
        $entity = $args->getObject();
        if (!$entity instanceof FarmScoped || null !== $entity->getFarm()) {
            return;
        }

        $farmId = $this->context->farmId();
        if (null === $farmId) {
            if (!$entity instanceof AuditLog) {
                throw new \LogicException(\sprintf('Nie można zapisać %s bez wybranego gospodarstwa.', $entity::class));
            }

            return;
        }

        $farm = $args->getObjectManager()->getReference(Farm::class, $farmId) ?? throw new \LogicException(\sprintf('Nie ma gospodarstwa %d.', $farmId));
        $entity->setFarm($farm);
    }
}
