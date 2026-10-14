<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Notification;
use App\Entity\User;
use App\Enum\NotificationType;
use App\Repository\NotificationRepository;
use App\Ui\ListFilters;
use App\Ui\Pager;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\UX\Turbo\TurboBundle;

#[Route('/powiadomienia')]
final class NotificationController extends AbstractController
{
    public function __construct(
        private readonly NotificationRepository $notifications,
        private readonly EntityManagerInterface $em,
    ) {
    }

    #[Route('', name: 'app_notification_index', methods: ['GET'])]
    public function index(Request $request, #[CurrentUser] User $user, ListFilters $listFilters): Response
    {
        if ($redirect = $listFilters->restore($request, 'notifications')) {
            return $redirect;
        }

        $filters = [
            'type' => $request->query->getString('typ') ?: null,
            'unread' => $request->query->getBoolean('nieprzeczytane'),
            'from' => self::date($request->query->getString('od')),
            'to' => self::date($request->query->getString('do')),
        ];

        $pager = Pager::fromQuery($this->notifications->createListQuery($user, $filters), $request->query->getInt('strona', 1));

        return $this->render('notification/index.html.twig', [
            'pager' => $pager,
            'filters' => $filters,
            'types' => NotificationType::cases(),
        ]);
    }

    #[Route('/dzwonek', name: 'app_notification_bell', methods: ['GET'])]
    public function bell(#[CurrentUser] User $user): Response
    {
        return $this->render('notification/_bell.html.twig', [
            'items' => $this->notifications->findLatest($user, 10),
            'unread' => $this->notifications->countUnread($user),
        ]);
    }

    #[Route('/{id}/otworz', name: 'app_notification_open', methods: ['GET'])]
    public function open(Notification $notification, #[CurrentUser] User $user): Response
    {
        if ($notification->getUser() !== $user) {
            throw $this->createNotFoundException();
        }

        $notification->markRead();
        $this->em->flush();

        return null !== $notification->getLink()
            ? $this->redirect($notification->getLink())
            : $this->redirectToRoute('app_notification_index');
    }

    #[Route('/przeczytane', name: 'app_notification_read_all', methods: ['POST'])]
    public function readAll(Request $request, #[CurrentUser] User $user): Response
    {
        if (!$this->isCsrfTokenValid('notifications', $request->getPayload()->getString('_token'))) {
            throw $this->createAccessDeniedException();
        }

        $count = $this->notifications->markAllRead($user);
        if ($count > 0) {
            $this->addFlash('success', 'Oznaczono powiadomienia jako przeczytane.');
        }

        // Z rozwiniętego dzwonka: odświeżamy całą stronę, żeby zgasły też liczniki w menu i na dzwonku.
        if ('notification-bell' === $request->headers->get('Turbo-Frame') && TurboBundle::STREAM_FORMAT === $request->getPreferredFormat()) {
            $request->setRequestFormat(TurboBundle::STREAM_FORMAT);

            return $this->render('_partials/refresh.stream.html.twig');
        }

        $referer = $request->headers->get('referer');

        return null !== $referer && str_starts_with($referer, $request->getSchemeAndHttpHost())
            ? $this->redirect($referer)
            : $this->redirectToRoute('app_notification_index');
    }

    private static function date(string $value): ?\DateTimeImmutable
    {
        $date = '' !== $value ? \DateTimeImmutable::createFromFormat('!Y-m-d', $value) : false;

        return false === $date ? null : $date;
    }
}
