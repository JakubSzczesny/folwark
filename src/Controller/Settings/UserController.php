<?php

declare(strict_types=1);

namespace App\Controller\Settings;

use App\Audit\AuditLogger;
use App\Entity\User;
use App\Enum\UserRole;
use App\Form\UserType;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormError;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/ustawienia/uzytkownicy')]
final class UserController extends AbstractController
{
    public function __construct(
        private readonly UserRepository $users,
        private readonly EntityManagerInterface $em,
        private readonly UserPasswordHasherInterface $hasher,
        private readonly AuditLogger $audit,
    ) {
    }

    #[Route('', name: 'app_settings_users', methods: ['GET'])]
    public function index(): Response
    {
        return $this->render('settings/users/index.html.twig', ['users' => $this->users->findAllOrdered()]);
    }

    #[Route('/nowy', name: 'app_settings_user_new', methods: ['GET', 'POST'])]
    public function new(Request $request): Response
    {
        return $this->handle(new User(), $request, true);
    }

    #[Route('/{id}', name: 'app_settings_user_edit', methods: ['GET', 'POST'])]
    public function edit(User $user, Request $request): Response
    {
        return $this->handle($user, $request, false);
    }

    private function handle(User $user, Request $request, bool $isNew): Response
    {
        $before = ['role' => $user->getRole()->value, 'active' => $user->isActive(), 'email' => $user->getEmail()];
        $form = $this->createForm(UserType::class, $user, ['require_password' => $isNew]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $lostOwner = !$isNew && UserRole::Owner->value === $before['role'] && $before['active']
                && (UserRole::Owner !== $user->getRole() || !$user->isActive());
            if ($lostOwner && $this->users->countActiveOwners() <= 1) {
                $form->get('role')->addError(new FormError('Musi zostać co najmniej jeden aktywny właściciel.'));
            } else {
                $password = (string) $form->get('plainPassword')->getData();
                if ('' !== $password) {
                    $user->setPassword($this->hasher->hashPassword($user, $password));
                }

                $this->em->persist($user);
                $this->em->flush();

                $after = ['role' => $user->getRole()->value, 'active' => $user->isActive(), 'email' => $user->getEmail()];
                $this->audit->log('User', $user->getId(), $isNew ? 'create' : 'update', array_filter([
                    'changes' => array_diff_assoc($after, $before) ?: null,
                    'password' => '' !== $password ? 'zmienione' : null,
                ]));
                $this->em->flush();

                $this->addFlash('success', $isNew ? 'Dodano użytkownika.' : 'Zapisano zmiany.');

                return $this->redirectToRoute('app_settings_users', [], Response::HTTP_SEE_OTHER);
            }
        }

        return $this->render('settings/users/form.html.twig', ['form' => $form, 'user' => $user, 'is_new' => $isNew]);
    }
}
