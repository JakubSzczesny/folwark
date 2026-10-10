<?php

declare(strict_types=1);

namespace App\Security;

use App\Entity\User;
use App\Farm\FarmContext;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Vote;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;
use Symfony\Component\Security\Core\Role\RoleHierarchyInterface;

/**
 * Uprawnienia wynikają z roli użytkownika w bieżącym gospodarstwie.
 *
 * @extends Voter<string, mixed>
 */
final class PermissionVoter extends Voter
{
    public function __construct(
        private readonly RoleHierarchyInterface $roleHierarchy,
        private readonly FarmContext $farmContext,
    ) {
    }

    protected function supports(string $attribute, mixed $subject): bool
    {
        return \array_key_exists($attribute, Permission::REQUIRED_ROLE);
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token, ?Vote $vote = null): bool
    {
        $user = $token->getUser();
        $role = $this->farmContext->getRole();
        if (!$user instanceof User || !$user->isActive() || null === $role) {
            return false;
        }

        $required = Permission::REQUIRED_ROLE[$attribute];
        if (null === $required) {
            return true;
        }

        return \in_array($required->value, $this->roleHierarchy->getReachableRoleNames([$role->value]), true);
    }
}
