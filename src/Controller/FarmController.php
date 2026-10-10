<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Farm;
use App\Entity\User;
use App\Farm\FarmContext;
use App\Farm\FarmCreator;
use App\Farm\FarmSwitcher;
use App\Form\FarmType;
use App\Repository\FarmMembershipRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

/**
 * Wybór gospodarstwa po zalogowaniu, przełączanie kontekstu i zakładanie nowych gospodarstw.
 */
#[Route('/gospodarstwa')]
final class FarmController extends AbstractController
{
    #[Route('', name: 'app_farm_index', methods: ['GET'])]
    public function index(#[CurrentUser] User $user, FarmMembershipRepository $memberships, FarmContext $context): Response
    {
        return $this->render('farm/index.html.twig', [
            'memberships' => $memberships->findForUser($user),
            'member_counts' => $memberships->countMembersByFarm(),
            'current' => $context->getFarm(),
        ]);
    }

    #[Route('/nowe', name: 'app_farm_new', methods: ['GET', 'POST'])]
    public function new(Request $request, #[CurrentUser] User $user, FarmCreator $creator, FarmSwitcher $switcher): Response
    {
        $farm = new Farm();
        $form = $this->createForm(FarmType::class, $farm);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $created = $creator->create($farm->getName(), $farm->getNip(), $user);
            $switcher->switchTo($user, (int) $created->getId());
            $this->addFlash('success', \sprintf('Utworzono gospodarstwo „%s”. Jesteś jego właścicielem – członków dodasz w Ustawieniach.', $created->getName()));

            return $this->redirectToRoute('app_dashboard', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('farm/new.html.twig', ['form' => $form]);
    }

    #[Route('/{id}/wybierz', name: 'app_farm_select', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function select(int $id, Request $request, #[CurrentUser] User $user, FarmSwitcher $switcher): Response
    {
        if (!$this->isCsrfTokenValid('farm-select', $request->getPayload()->getString('_token'))) {
            throw $this->createAccessDeniedException();
        }

        if (null === $switcher->switchTo($user, $id)) {
            $this->addFlash('error', 'Nie masz dostępu do tego gospodarstwa.');

            return $this->redirectToRoute('app_farm_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->redirectToRoute('app_dashboard', [], Response::HTTP_SEE_OTHER);
    }
}
