<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\User;
use App\Entity\WeighbridgeTask;
use App\Form\WeighbridgeTaskType;
use App\Repository\WeighbridgeTaskRepository;
use App\Security\Permission;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/waga')]
final class WeighbridgeController extends AbstractController
{
    public function __construct(
        private readonly WeighbridgeTaskRepository $tasks,
        private readonly EntityManagerInterface $em,
    ) {
    }

    #[Route('', name: 'app_weighbridge', methods: ['GET'])]
    public function index(): Response
    {
        return $this->render('weighbridge/index.html.twig', [
            'groups' => $this->tasks->findGrouped(),
            'progress' => $this->tasks->progress(),
        ]);
    }

    #[Route('/{id}/odhacz', name: 'app_weighbridge_toggle', methods: ['POST'])]
    #[IsGranted(Permission::WEIGHBRIDGE_CHECK)]
    public function toggle(WeighbridgeTask $task, Request $request, #[CurrentUser] User $user): Response
    {
        $this->assertToken($request);

        if ($task->isDone()) {
            $task->markUndone();
        } else {
            $task->markDone($user);
        }
        $this->em->flush();

        return $this->redirectToRoute('app_weighbridge', [], Response::HTTP_SEE_OTHER);
    }

    #[Route('/{id}/notatka', name: 'app_weighbridge_note', methods: ['POST'])]
    #[IsGranted(Permission::WEIGHBRIDGE_CHECK)]
    public function note(WeighbridgeTask $task, Request $request): Response
    {
        $this->assertToken($request);

        $note = trim($request->getPayload()->getString('note'));
        $task->setNote('' === $note ? null : $note);
        $this->em->flush();

        return $this->redirectToRoute('app_weighbridge', [], Response::HTTP_SEE_OTHER);
    }

    #[Route('/nowy', name: 'app_weighbridge_new', methods: ['GET', 'POST'])]
    #[IsGranted(Permission::WEIGHBRIDGE_MANAGE)]
    public function new(Request $request): Response
    {
        $task = (new WeighbridgeTask())->setPosition($this->tasks->nextPosition());

        return $this->handleForm($task, $request, 'Nowy punkt');
    }

    #[Route('/{id}/edycja', name: 'app_weighbridge_edit', methods: ['GET', 'POST'])]
    #[IsGranted(Permission::WEIGHBRIDGE_MANAGE)]
    public function edit(WeighbridgeTask $task, Request $request): Response
    {
        return $this->handleForm($task, $request, 'Edycja punktu');
    }

    #[Route('/{id}/usun', name: 'app_weighbridge_delete', methods: ['POST'])]
    #[IsGranted(Permission::WEIGHBRIDGE_MANAGE)]
    public function delete(WeighbridgeTask $task, Request $request): Response
    {
        $this->assertToken($request);
        $this->em->remove($task);
        $this->em->flush();
        $this->addFlash('success', 'Usunięto punkt z listy.');

        return $this->redirectToRoute('app_weighbridge', [], Response::HTTP_SEE_OTHER);
    }

    private function handleForm(WeighbridgeTask $task, Request $request, string $title): Response
    {
        $form = $this->createForm(WeighbridgeTaskType::class, $task);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->em->persist($task);
            $this->em->flush();

            return $this->redirectToRoute('app_weighbridge', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('weighbridge/form.html.twig', [
            'form' => $form,
            'title' => $title,
            'task' => $task,
            'groups' => $this->tasks->groupNames(),
        ]);
    }

    private function assertToken(Request $request): void
    {
        if (!$this->isCsrfTokenValid('weighbridge', $request->getPayload()->getString('_token'))) {
            throw $this->createAccessDeniedException('Nieprawidłowy token formularza.');
        }
    }
}
