<?php

declare(strict_types=1);

namespace App\Notification;

use App\Entity\Notification;
use App\Entity\User;
use App\Enum\NotificationPriority;
use App\Enum\NotificationType;
use App\Farm\FarmContext;
use App\Repository\FarmMembershipRepository;
use App\Repository\NotificationPreferenceRepository;
use App\Repository\NotificationRepository;
use Doctrine\ORM\EntityManagerInterface;

class Notifier
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly FarmMembershipRepository $memberships,
        private readonly FarmContext $context,
        private readonly NotificationRepository $notifications,
        private readonly NotificationPreferenceRepository $preferences,
    ) {
    }

    /**
     * Tworzy powiadomienie w bieżącym gospodarstwie dla domyślnych odbiorców typu (lub podanych użytkowników).
     * Klucz deduplikacji sprawia, że to samo zdarzenie nie trafia do użytkownika drugi raz.
     *
     * @param list<User>|null $recipients
     *
     * @return int liczba utworzonych powiadomień
     */
    public function notify(
        NotificationType $type,
        NotificationPriority $priority,
        string $title,
        ?string $body = null,
        ?string $link = null,
        ?string $dedupKey = null,
        ?array $recipients = null,
    ): int {
        $farm = $this->context->getFarm();
        if (null === $farm) {
            throw new \LogicException('Powiadomienia tworzy się w kontekście gospodarstwa.');
        }

        $recipients ??= $this->memberships->findActiveUsers($farm, $type->defaultRecipients());
        $disabled = $this->preferences->findDisabledUserIds($type);
        $created = 0;

        foreach ($recipients as $user) {
            if (!$user->isActive() || \in_array($user->getId(), $disabled, true)) {
                continue;
            }
            if (null !== $dedupKey && $this->notifications->existsForUser($user, $dedupKey)) {
                continue;
            }

            $this->em->persist(new Notification($user, $type, $priority, $title, $body, $link, $dedupKey));
            ++$created;
        }

        if ($created > 0) {
            $this->em->flush();
        }

        return $created;
    }
}
