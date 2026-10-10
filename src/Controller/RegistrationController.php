<?php

declare(strict_types=1);

namespace App\Controller;

use App\Audit\AuditLogger;
use App\Entity\User;
use App\Form\RegistrationType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Samodzielna rejestracja konta. Konto bez gospodarstwa nic nie widzi – dostęp daje
 * założenie własnego gospodarstwa albo dodanie przez właściciela istniejącego.
 */
final class RegistrationController extends AbstractController
{
    #[Route('/rejestracja', name: 'app_register', methods: ['GET', 'POST'])]
    public function register(
        Request $request,
        UserPasswordHasherInterface $hasher,
        EntityManagerInterface $em,
        Security $security,
        AuditLogger $audit,
    ): Response {
        if (null !== $this->getUser()) {
            return $this->redirectToRoute('app_farm_index');
        }

        $user = new User();
        $form = $this->createForm(RegistrationType::class, $user);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $user->setPassword($hasher->hashPassword($user, (string) $form->get('plainPassword')->getData()));
            $em->persist($user);
            $em->flush();
            $audit->log('User', $user->getId(), 'register', ['email' => $user->getEmail()], $user);
            $em->flush();

            $security->login($user, 'form_login', 'main');
            $this->addFlash('success', 'Konto założone. Utwórz gospodarstwo albo poproś właściciela gospodarstwa o dodanie Cię.');

            return $this->redirectToRoute('app_farm_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('security/register.html.twig', ['form' => $form]);
    }
}
