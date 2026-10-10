<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\NotificationPreference;
use App\Entity\TrustedDevice;
use App\Entity\User;
use App\Enum\NotificationType;
use App\Form\ChangePasswordType;
use App\Form\TrustDeviceType;
use App\Repository\NotificationPreferenceRepository;
use App\Repository\TrustedDeviceRepository;
use App\Security\TrustedDeviceManager;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

#[Route('/konto')]
final class AccountController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $em,
    ) {
    }

    #[Route('', name: 'app_account', methods: ['GET', 'POST'])]
    public function index(
        Request $request,
        #[CurrentUser] User $user,
        UserPasswordHasherInterface $hasher,
        TrustedDeviceManager $devices,
        TrustedDeviceRepository $deviceRepository,
        NotificationPreferenceRepository $preferences,
    ): Response {
        $passwordForm = $this->createForm(ChangePasswordType::class);
        $passwordForm->handleRequest($request);
        if ($passwordForm->isSubmitted() && $passwordForm->isValid()) {
            $user->setPassword($hasher->hashPassword($user, $passwordForm->get('newPassword')->getData()));
            $this->em->flush();
            $this->addFlash('success', 'Hasło zostało zmienione.');

            return $this->redirectToRoute('app_account', [], Response::HTTP_SEE_OTHER);
        }

        $deviceForm = $this->createForm(TrustDeviceType::class);
        $deviceForm->handleRequest($request);
        if ($deviceForm->isSubmitted() && $deviceForm->isValid()) {
            $cookie = $devices->trust($user, $deviceForm->get('name')->getData(), $deviceForm->get('pin')->getData(), $request);
            $this->addFlash('success', 'To urządzenie jest zaufane. Następnym razem zalogujesz się PIN-em.');
            $response = $this->redirectToRoute('app_account', [], Response::HTTP_SEE_OTHER);
            $response->headers->setCookie($cookie);

            return $response;
        }

        return $this->render('account/index.html.twig', [
            'password_form' => $passwordForm,
            'device_form' => $deviceForm,
            'devices' => $deviceRepository->findBy(['user' => $user], ['createdAt' => 'DESC']),
            'current_device' => $devices->findForRequest($request),
            'preferences' => $preferences->findForUser($user),
            'types' => NotificationType::cases(),
        ]);
    }

    #[Route('/urzadzenia/{id}/usun', name: 'app_account_device_revoke', methods: ['POST'])]
    public function revokeDevice(TrustedDevice $device, Request $request, #[CurrentUser] User $user, TrustedDeviceManager $devices): Response
    {
        if ($device->getUser() !== $user || !$this->isCsrfTokenValid('device', $request->getPayload()->getString('_token'))) {
            throw $this->createAccessDeniedException();
        }

        $devices->revoke($device);
        $this->addFlash('success', 'Urządzenie usunięte z zaufanych.');

        return $this->redirectToRoute('app_account', [], Response::HTTP_SEE_OTHER);
    }

    #[Route('/powiadomienia', name: 'app_account_notifications', methods: ['POST'])]
    public function notifications(Request $request, #[CurrentUser] User $user, NotificationPreferenceRepository $repository): Response
    {
        if (!$this->isCsrfTokenValid('notification-preferences', $request->getPayload()->getString('_token'))) {
            throw $this->createAccessDeniedException();
        }

        $existing = $repository->findForUser($user);
        $enabled = $request->getPayload()->all('enabled');
        $email = $request->getPayload()->all('email');

        foreach (NotificationType::cases() as $type) {
            $preference = $existing[$type->value] ?? new NotificationPreference($user, $type);
            $preference->setEnabled(isset($enabled[$type->value]));
            $preference->setEmail(isset($email[$type->value]));
            $this->em->persist($preference);
        }
        $this->em->flush();
        $this->addFlash('success', 'Zapisano ustawienia powiadomień.');

        return $this->redirectToRoute('app_account', [], Response::HTTP_SEE_OTHER);
    }
}
