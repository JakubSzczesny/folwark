<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\ContactPerson;
use App\Entity\Contractor;
use App\Security\Permission;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * Zakładka „Osoby” karty kontrahenta: dodawanie, edycja i usuwanie osób w oknie dialogowym.
 */
#[Route('/kontrahenci/{id}/osoby', requirements: ['id' => '\d+'])]
#[IsGranted(Permission::CONTRACTOR_EDIT)]
final class ContactPersonController extends AbstractController
{
    use ModalResponses;

    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly ValidatorInterface $validator,
    ) {
    }

    #[Route('/nowa', name: 'app_contact_person_new', methods: ['GET', 'POST'])]
    public function new(Contractor $contractor, Request $request): Response
    {
        return $this->handle(new ContactPerson($contractor), $request);
    }

    #[Route('/{person}', name: 'app_contact_person_edit', requirements: ['person' => '\d+'], methods: ['GET', 'POST'])]
    public function edit(Contractor $contractor, ContactPerson $person, Request $request): Response
    {
        $this->assertBelongs($contractor, $person);

        return $this->handle($person, $request);
    }

    #[Route('/{person}/usun', name: 'app_contact_person_delete', requirements: ['person' => '\d+'], methods: ['POST'])]
    public function delete(Contractor $contractor, ContactPerson $person, Request $request): Response
    {
        $this->assertBelongs($contractor, $person);
        $this->assertToken($request);
        $this->em->remove($person);
        $this->em->flush();
        $this->addFlash('success', \sprintf('Usunięto osobę „%s”.', $person->getName()));

        return $this->visit($request, $this->tabUrl($contractor));
    }

    private function handle(ContactPerson $person, Request $request): Response
    {
        $errors = [];
        if ($request->isMethod('POST')) {
            $this->assertToken($request);
            $payload = $request->getPayload();
            $person->setName($payload->getString('name'))
                ->setPosition($payload->getString('position'))
                ->setPhone($payload->getString('phone'))
                ->setEmail($payload->getString('email'))
                ->setNote($payload->getString('note'));

            foreach ($this->validator->validate($person) as $violation) {
                $errors[$violation->getPropertyPath()] ??= (string) $violation->getMessage();
            }
            if ([] === $errors) {
                $new = null === $person->getId();
                $this->em->persist($person);
                $this->em->flush();
                $this->addFlash('success', \sprintf($new ? 'Dodano osobę „%s”.' : 'Zapisano osobę „%s”.', $person->getName()));

                return $this->visit($request, $this->tabUrl($person->getContractor()));
            }
        }

        return $this->render('contractor/contact_person.html.twig', ['person' => $person, 'errors' => $errors], new Response(null, [] !== $errors ? 422 : 200));
    }

    private function tabUrl(Contractor $contractor): string
    {
        return $this->generateUrl('app_contractor_show', ['id' => $contractor->getId(), 'zakladka' => 'osoby']);
    }

    private function assertBelongs(Contractor $contractor, ContactPerson $person): void
    {
        if ($person->getContractor() !== $contractor) {
            throw $this->createNotFoundException();
        }
    }

    private function assertToken(Request $request): void
    {
        if (!$this->isCsrfTokenValid('contact-person', $request->getPayload()->getString('_token'))) {
            throw $this->createAccessDeniedException();
        }
    }
}
