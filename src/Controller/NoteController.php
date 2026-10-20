<?php

declare(strict_types=1);

namespace App\Controller;

use App\Audit\AuditLogger;
use App\Entity\Note;
use App\Entity\User;
use App\Note\NoteSubjects;
use App\Security\NoteVoter;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Notatki przy kontrahentach, maszynach i fakturach: dodawanie, edycja i usuwanie wpisów.
 */
#[Route('/notatki')]
final class NoteController extends AbstractController
{
    use ModalResponses;

    public function __construct(
        private readonly NoteSubjects $subjects,
        private readonly EntityManagerInterface $em,
        private readonly AuditLogger $audit,
    ) {
    }

    #[Route('/{type}/{id}', name: 'app_note_add', requirements: ['type' => 'contractor|machine|invoice', 'id' => '\d+'], methods: ['POST'])]
    public function add(string $type, int $id, Request $request, #[CurrentUser] User $user): Response
    {
        $this->denyAccessUnlessGranted(NoteSubjects::permission($type, 'write'));
        $this->assertToken($request);
        if (null === $this->subjects->find($type, $id)) {
            throw $this->createNotFoundException();
        }

        $body = trim($request->getPayload()->getString('body'));
        if ('' === $body) {
            $this->addFlash('warning', 'Notatka jest pusta – nic nie dodano.');
        } else {
            $note = new Note($type, $id, mb_substr($body, 0, Note::MAX_LENGTH), $user);
            $this->em->persist($note);
            $this->em->flush();
            $this->audit->log('Note', $note->getId(), 'create', ['subject' => $type.':'.$id]);
            $this->em->flush();
            $this->addFlash('success', 'Dodano notatkę.');
        }

        return $this->redirect($this->subjects->url($type, $id), Response::HTTP_SEE_OTHER);
    }

    #[Route('/{id}/edycja', name: 'app_note_edit', requirements: ['id' => '\d+'], methods: ['GET', 'POST'])]
    #[IsGranted(NoteVoter::EDIT, 'note')]
    public function edit(Note $note, Request $request): Response
    {
        $error = null;
        if ($request->isMethod('POST')) {
            $this->assertToken($request);
            $body = trim($request->getPayload()->getString('body'));
            if ('' === $body) {
                $error = 'Wpisz treść notatki albo ją usuń.';
            } else {
                $note->edit(mb_substr($body, 0, Note::MAX_LENGTH));
                $this->audit->log('Note', $note->getId(), 'update', ['subject' => $note->getSubjectType().':'.$note->getSubjectId()]);
                $this->em->flush();
                $this->addFlash('success', 'Zapisano notatkę.');

                return $this->visit($request, $this->subjects->url($note->getSubjectType(), $note->getSubjectId()));
            }
        }

        return $this->render('note/edit.html.twig', ['note' => $note, 'error' => $error], new Response(null, null !== $error ? 422 : 200));
    }

    #[Route('/{id}/usun', name: 'app_note_delete', requirements: ['id' => '\d+'], methods: ['POST'])]
    #[IsGranted(NoteVoter::EDIT, 'note')]
    public function delete(Note $note, Request $request): Response
    {
        $this->assertToken($request);
        $url = $this->subjects->url($note->getSubjectType(), $note->getSubjectId());
        $this->audit->log('Note', $note->getId(), 'delete', ['subject' => $note->getSubjectType().':'.$note->getSubjectId(), 'body' => mb_substr($note->getBody(), 0, 200)]);
        $this->em->remove($note);
        $this->em->flush();
        $this->addFlash('success', 'Usunięto notatkę.');

        return $this->visit($request, $url);
    }

    private function assertToken(Request $request): void
    {
        if (!$this->isCsrfTokenValid('note', $request->getPayload()->getString('_token'))) {
            throw $this->createAccessDeniedException();
        }
    }
}
