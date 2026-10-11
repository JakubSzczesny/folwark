<?php

declare(strict_types=1);

namespace App\Controller\Machine;

use App\Entity\Machine;
use App\Entity\MachineService;
use App\Entity\MachineServiceAttachment;
use App\Entity\User;
use App\Form\Field\PerformerAutocompleteField;
use App\Form\MachineServiceType;
use App\Machine\ServiceRecorder;
use App\Repository\ContractorRepository;
use App\Security\Permission;
use App\Storage\FileStorage;
use App\Storage\Thumbnails;
use App\Ui\RecentChoices;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/maszyny')]
#[IsGranted(Permission::MACHINE_VIEW)]
final class MachineServiceController extends AbstractController
{
    public function __construct(
        private readonly ServiceRecorder $recorder,
    ) {
    }

    #[Route('/{id}/serwis/nowy', name: 'app_machine_service_new', requirements: ['id' => '\d+'], methods: ['GET', 'POST'])]
    #[IsGranted(Permission::MACHINE_EDIT)]
    public function new(Machine $machine, Request $request, #[CurrentUser] User $user, RecentChoices $recent): Response
    {
        return $this->handleForm($this->recorder->prepare($machine, $user), $request, $recent);
    }

    #[Route('/serwis/{id}/edycja', name: 'app_machine_service_edit', requirements: ['id' => '\d+'], methods: ['GET', 'POST'])]
    #[IsGranted(Permission::MACHINE_EDIT)]
    public function edit(MachineService $service, Request $request, RecentChoices $recent): Response
    {
        return $this->handleForm($service, $request, $recent);
    }

    #[Route('/serwis/{id}/usun', name: 'app_machine_service_delete', requirements: ['id' => '\d+'], methods: ['POST'])]
    #[IsGranted(Permission::MACHINE_EDIT)]
    public function delete(MachineService $service, Request $request): Response
    {
        $this->assertToken($request);
        $machine = $service->getMachine();
        $this->recorder->delete($service);
        $this->addFlash('success', 'Usunięto wpis serwisowy.');

        return $this->redirectToRoute('app_machine_service', ['id' => $machine->getId()], Response::HTTP_SEE_OTHER);
    }

    /**
     * Opcje listy faktur do wpisu: faktury kosztowe wykonawcy z ±14 dni od daty serwisu.
     */
    #[Route('/serwis/faktury', name: 'app_machine_service_invoices', methods: ['GET'])]
    #[IsGranted(Permission::INVOICE_VIEW)]
    public function invoiceOptions(Request $request, ContractorRepository $contractors): Response
    {
        $contractor = (int) $request->query->getString('kontrahent') ? $contractors->find((int) $request->query->getString('kontrahent')) : null;
        $date = \DateTimeImmutable::createFromFormat('!Y-m-d', $request->query->getString('data')) ?: null;

        return $this->render('machine/service/_invoice_options.html.twig', [
            'invoices' => $this->recorder->suggestInvoices($contractor, $date),
            'contractor' => $contractor,
            'date' => $date,
            'selected' => (int) $request->query->getString('wybrana') ?: null,
        ]);
    }

    #[Route('/serwis/zalacznik/{id}', name: 'app_machine_service_attachment', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function attachment(MachineServiceAttachment $attachment, FileStorage $storage): Response
    {
        return $storage->response($attachment->getPath(), $attachment->getName(), true);
    }

    #[Route('/serwis/zalacznik/{id}/{filter}', name: 'app_machine_service_thumbnail', requirements: ['id' => '\d+', 'filter' => 'thumb|card|photo'], methods: ['GET'])]
    public function thumbnail(MachineServiceAttachment $attachment, string $filter, Thumbnails $thumbnails): Response
    {
        if (!$attachment->isPhoto()) {
            throw $this->createNotFoundException();
        }

        return $thumbnails->response($attachment->getPath(), $filter);
    }

    #[Route('/serwis/zalacznik/{id}/usun', name: 'app_machine_service_attachment_delete', requirements: ['id' => '\d+'], methods: ['POST'])]
    #[IsGranted(Permission::MACHINE_EDIT)]
    public function deleteAttachment(MachineServiceAttachment $attachment, Request $request): Response
    {
        $this->assertToken($request);
        $service = $attachment->getService();
        $this->recorder->deleteAttachment($attachment);

        return $this->redirectToRoute('app_machine_service_edit', ['id' => $service->getId()], Response::HTTP_SEE_OTHER);
    }

    private function handleForm(MachineService $service, Request $request, RecentChoices $recent): Response
    {
        $isNew = null === $service->getId();
        $withInvoice = $this->isGranted(Permission::INVOICE_VIEW);
        $form = $this->createForm(MachineServiceType::class, $service, [
            'with_amounts' => $this->isGranted(Permission::AMOUNTS_VIEW),
            'with_invoice' => $withInvoice,
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $files = array_values(array_filter((array) $form->get('newAttachments')->getData(), static fn (mixed $f): bool => $f instanceof UploadedFile));
            $this->recorder->save($service, $files);
            $recent->remember(PerformerAutocompleteField::RECENT_KIND, $service->getContractor()?->getId());
            $this->addFlash('success', $isNew ? 'Dodano wpis serwisowy.' : 'Zapisano wpis serwisowy.');

            return $this->redirectToRoute('app_machine_service', ['id' => $service->getMachine()->getId()], Response::HTTP_SEE_OTHER);
        }

        return $this->render('machine/service/form.html.twig', [
            'form' => $form,
            'service' => $service,
            'machine' => $service->getMachine(),
            'invoices' => $withInvoice ? $this->recorder->suggestInvoices($service->getContractor(), $service->getDate()) : [],
        ], new Response(null, $form->isSubmitted() ? 422 : 200));
    }

    private function assertToken(Request $request): void
    {
        if (!$this->isCsrfTokenValid('machine-service', $request->getPayload()->getString('_token'))) {
            throw $this->createAccessDeniedException();
        }
    }
}
