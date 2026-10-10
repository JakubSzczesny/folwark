<?php

declare(strict_types=1);

namespace App\Controller\Settings;

use App\Audit\AuditLogger;
use App\Entity\FarmMembership;
use App\Entity\User;
use App\Enum\UserRole;
use App\Farm\FarmContext;
use App\Form\MemberType;
use App\Repository\FarmMembershipRepository;
use App\Repository\UserRepository;
use App\Security\Permission;
use App\Setup\UserCreator;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormError;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/ustawienia/czlonkowie')]
#[IsGranted(Permission::SETTINGS)]
final class MemberController extends AbstractController
{
    public function __construct(
        private readonly FarmMembershipRepository $memberships,
        private readonly FarmContext $context,
        private readonly EntityManagerInterface $em,
        private readonly AuditLogger $audit,
    ) {
    }

    #[Route('', name: 'app_settings_members', methods: ['GET', 'POST'])]
    public function index(Request $request, UserRepository $users, UserCreator $creator): Response
    {
        $farm = $this->context->farm();
        $form = $this->createForm(MemberType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $email = mb_strtolower(trim((string) $form->get('email')->getData()));
            $role = $form->get('role')->getData();
            \assert($role instanceof UserRole);
            $user = $users->findOneByEmail($email);

            if (null === $user) {
                $name = trim((string) $form->get('fullName')->getData());
                $password = (string) $form->get('password')->getData();
                if ('' === $name || '' === $password) {
                    $form->get('email')->addError(new FormError('Nie ma konta o tym adresie. Podaj imię i nazwisko oraz hasło startowe, żeby je założyć – albo poproś tę osobę o rejestrację.'));
                } else {
                    try {
                        $user = $creator->create($email, $name, $password);
                    } catch (\InvalidArgumentException $e) {
                        $form->get('password')->addError(new FormError($e->getMessage()));
                    }
                }
            } elseif (null !== $this->memberships->findOneForUser($user, (int) $farm->getId())) {
                $form->get('email')->addError(new FormError('Ta osoba już należy do gospodarstwa.'));
                $user = null;
            }

            if ($user instanceof User) {
                $this->em->persist(new FarmMembership($farm, $user, $role));
                $this->audit->log('FarmMembership', $user->getId(), 'create', ['email' => $user->getEmail(), 'role' => $role->value]);
                $this->em->flush();
                $this->addFlash('success', \sprintf('%s ma teraz dostęp do gospodarstwa (%s).', $user->getFullName(), $role->label()));

                return $this->redirectToRoute('app_settings_members', [], Response::HTTP_SEE_OTHER);
            }
        }

        return $this->render('settings/members.html.twig', [
            'form' => $form,
            'memberships' => $this->memberships->findByFarm($farm),
            'roles' => UserRole::cases(),
        ], new Response(null, $form->isSubmitted() && !$form->isValid() ? 422 : 200));
    }

    #[Route('/{id}/rola', name: 'app_settings_member_role', methods: ['POST'])]
    public function changeRole(FarmMembership $membership, Request $request): Response
    {
        $this->assertOwnFarm($membership, $request);
        $role = UserRole::tryFrom($request->getPayload()->getString('role'));
        if (null === $role || $role === $membership->getRole()) {
            return $this->redirectToRoute('app_settings_members', [], Response::HTTP_SEE_OTHER);
        }

        if ($membership->isOwner() && $this->memberships->countOwners($membership->getFarm()) <= 1) {
            $this->addFlash('error', 'Gospodarstwo musi mieć co najmniej jednego właściciela.');

            return $this->redirectToRoute('app_settings_members', [], Response::HTTP_SEE_OTHER);
        }

        $old = $membership->getRole();
        $membership->setRole($role);
        $this->audit->log('FarmMembership', $membership->getUser()->getId(), 'role', ['old' => $old->value, 'new' => $role->value]);
        $this->em->flush();
        $this->addFlash('success', \sprintf('Zmieniono rolę: %s – %s.', $membership->getUser()->getFullName(), $role->label()));

        return $this->redirectToRoute('app_settings_members', [], Response::HTTP_SEE_OTHER);
    }

    #[Route('/{id}/usun', name: 'app_settings_member_remove', methods: ['POST'])]
    public function remove(FarmMembership $membership, Request $request, #[CurrentUser] User $current): Response
    {
        $this->assertOwnFarm($membership, $request);

        if ($membership->isOwner() && $this->memberships->countOwners($membership->getFarm()) <= 1) {
            $this->addFlash('error', 'Nie można usunąć jedynego właściciela gospodarstwa.');

            return $this->redirectToRoute('app_settings_members', [], Response::HTTP_SEE_OTHER);
        }

        $user = $membership->getUser();
        $this->audit->log('FarmMembership', $user->getId(), 'delete', ['email' => $user->getEmail()]);
        $this->em->remove($membership);
        $this->em->flush();
        $this->addFlash('success', \sprintf('%s nie ma już dostępu do gospodarstwa.', $user->getFullName()));

        if ($user === $current) {
            return $this->redirectToRoute('app_farm_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->redirectToRoute('app_settings_members', [], Response::HTTP_SEE_OTHER);
    }

    private function assertOwnFarm(FarmMembership $membership, Request $request): void
    {
        if ($membership->getFarm()->getId() !== $this->context->farmId() || !$this->isCsrfTokenValid('member', $request->getPayload()->getString('_token'))) {
            throw $this->createNotFoundException();
        }
    }
}
