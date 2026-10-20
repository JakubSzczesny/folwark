<?php

declare(strict_types=1);

namespace App\Security;

use App\Entity\CalendarEvent;
use App\Entity\User;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\AccessDecisionManagerInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Vote;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

/**
 * Dostęp do pojedynczego wydarzenia:
 * - widzą je wszyscy z CALENDAR_VIEW, prywatne – tylko autor,
 * - edytuje i usuwa autor (CALENDAR_EDIT) albo właściciel (CALENDAR_MANAGE), prywatne – tylko autor.
 *
 * @extends Voter<string, CalendarEvent>
 */
final class CalendarEventVoter extends Voter
{
    public const string VIEW = 'CALENDAR_EVENT_VIEW';
    public const string EDIT = 'CALENDAR_EVENT_EDIT';

    public function __construct(
        private readonly AccessDecisionManagerInterface $decisions,
    ) {
    }

    protected function supports(string $attribute, mixed $subject): bool
    {
        return \in_array($attribute, [self::VIEW, self::EDIT], true) && $subject instanceof CalendarEvent;
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token, ?Vote $vote = null): bool
    {
        $user = $token->getUser();
        if (!$user instanceof User || !$this->decisions->decide($token, [Permission::CALENDAR_VIEW])) {
            return false;
        }

        $own = $subject->isCreatedBy($user);
        if ($subject->isPrivate() && !$own) {
            return false;
        }

        if (self::VIEW === $attribute) {
            return true;
        }

        return ($own && $this->decisions->decide($token, [Permission::CALENDAR_EDIT]))
            || $this->decisions->decide($token, [Permission::CALENDAR_MANAGE]);
    }
}
