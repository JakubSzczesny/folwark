<?php

declare(strict_types=1);

namespace App\Farm;

use App\Entity\FarmMembership;
use App\Entity\User;
use App\Repository\FarmMembershipRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Wybór gospodarstwa, w którym użytkownik pracuje w tej sesji.
 */
final class FarmSwitcher
{
    public function __construct(
        private readonly FarmMembershipRepository $memberships,
        private readonly RequestStack $requestStack,
        private readonly FarmContext $context,
        private readonly EntityManagerInterface $em,
    ) {
    }

    public function switchTo(User $user, int $farmId): ?FarmMembership
    {
        $membership = $this->memberships->findOneForUser($user, $farmId);
        if (null === $membership) {
            return null;
        }

        $this->requestStack->getSession()->set(FarmContextSubscriber::SESSION_KEY, $farmId);
        $this->context->set($membership->getFarm(), $membership->getRole());
        $user->setLastFarm($membership->getFarm());
        $this->em->flush();

        return $membership;
    }

    public function leave(): void
    {
        $this->requestStack->getSession()->remove(FarmContextSubscriber::SESSION_KEY);
        $this->context->clear();
    }
}
