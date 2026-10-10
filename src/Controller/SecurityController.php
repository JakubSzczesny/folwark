<?php

declare(strict_types=1);

namespace App\Controller;

use App\Security\TrustedDeviceManager;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;

final class SecurityController extends AbstractController
{
    #[Route('/logowanie', name: 'app_login', methods: ['GET', 'POST'])]
    public function login(Request $request, AuthenticationUtils $authenticationUtils, TrustedDeviceManager $devices): Response
    {
        if (null !== $this->getUser()) {
            return $this->redirectToRoute('app_dashboard');
        }

        $device = $request->query->has('haslo') ? null : $devices->findForRequest($request);

        return $this->render('security/login.html.twig', [
            'last_username' => $authenticationUtils->getLastUsername(),
            'error' => $authenticationUtils->getLastAuthenticationError(),
            'device' => $device,
        ]);
    }

    #[Route('/pin', name: 'app_pin', methods: ['POST'])]
    public function pin(): never
    {
        throw new \LogicException('Obsługiwane przez PinAuthenticator.');
    }

    #[Route('/wyloguj', name: 'app_logout', methods: ['GET'])]
    public function logout(): never
    {
        throw new \LogicException('Obsługiwane przez firewall.');
    }
}
