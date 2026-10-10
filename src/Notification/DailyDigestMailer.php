<?php

declare(strict_types=1);

namespace App\Notification;

use App\Farm\FarmContext;
use App\Repository\NotificationPreferenceRepository;
use App\Repository\NotificationRepository;
use App\Settings\SettingKey;
use App\Settings\Settings;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Mailer\MailerInterface;

class DailyDigestMailer
{
    public function __construct(
        private readonly Settings $settings,
        private readonly NotificationRepository $notifications,
        private readonly NotificationPreferenceRepository $preferences,
        private readonly MailerInterface $mailer,
        private readonly EntityManagerInterface $em,
        private readonly FarmContext $context,
        #[Autowire('%env(MAILER_FROM)%')]
        private readonly string $from,
    ) {
    }

    /**
     * @return int liczba wysłanych wiadomości
     */
    public function send(): int
    {
        if (!$this->settings->bool(SettingKey::NOTIFY_DIGEST_EMAIL)) {
            return 0;
        }

        $sent = 0;
        foreach ($this->preferences->findEmailTypesByUser() as $entry) {
            $user = $entry['user'];
            $items = $this->notifications->findNotEmailed($user, $entry['types'], new \DateTimeImmutable('-2 days'));
            if ([] === $items) {
                continue;
            }

            $this->mailer->send((new TemplatedEmail())
                ->from($this->from)
                ->to($user->getEmail())
                ->subject(\sprintf('Folwark – podsumowanie dnia (%d)', \count($items)))
                ->htmlTemplate('email/digest.html.twig')
                ->context(['user' => $user, 'items' => $items, 'farm' => $this->context->getFarm()?->getName()]));

            foreach ($items as $item) {
                $item->markEmailed();
            }
            ++$sent;
        }

        $this->em->flush();

        return $sent;
    }
}
