<?php

declare(strict_types=1);

namespace App\Security;

use App\Entity\Note;
use App\Entity\User;
use App\Note\NoteSubjects;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\AccessDecisionManagerInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Vote;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

/**
 * Notatkę edytuje i usuwa jej autor (z prawem dodawania notatek) albo osoba z prawem zarządzania
 * obiektem (np. właściciel przy maszynach).
 *
 * @extends Voter<string, Note>
 */
final class NoteVoter extends Voter
{
    public const string EDIT = 'NOTE_EDIT';

    public function __construct(
        private readonly AccessDecisionManagerInterface $decisions,
    ) {
    }

    protected function supports(string $attribute, mixed $subject): bool
    {
        return self::EDIT === $attribute && $subject instanceof Note && NoteSubjects::exists($subject->getSubjectType());
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token, ?Vote $vote = null): bool
    {
        $user = $token->getUser();
        if (!$user instanceof User) {
            return false;
        }

        $type = $subject->getSubjectType();

        return ($subject->isAuthoredBy($user) && $this->decisions->decide($token, [NoteSubjects::permission($type, 'write')]))
            || $this->decisions->decide($token, [NoteSubjects::permission($type, 'manage')]);
    }
}
