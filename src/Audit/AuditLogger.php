<?php

declare(strict_types=1);

namespace App\Audit;

use App\Entity\AuditLog;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;

/**
 * Zapisuje zdarzenia do log_zdarzen. Wpis jest tylko persistowany –
 * zapis do bazy następuje razem z operacją, której dotyczy.
 */
class AuditLogger
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly Security $security,
    ) {
    }

    /**
     * @param array<string, mixed> $changes
     */
    public function log(string $entity, int|string|null $entityId, string $action, array $changes = [], ?User $user = null): void
    {
        $user ??= $this->currentUser();
        $this->em->persist(new AuditLog($user, $entity, (string) $entityId, $action, $changes));
    }

    private function currentUser(): ?User
    {
        $user = $this->security->getUser();

        return $user instanceof User ? $user : null;
    }
}
