<?php

declare(strict_types=1);

namespace App\Controller\Warehouse;

use App\Audit\AuditLogger;
use App\Controller\ModalResponses;
use App\Entity\StockDocument;
use App\Entity\User;
use App\Enum\DocumentStatus;
use App\Enum\DocumentType;
use App\Export\PdfRenderer;
use App\Form\StockDocumentType;
use App\Repository\StockDocumentRepository;
use App\Repository\StockMovementRepository;
use App\Repository\WarehouseRepository;
use App\Security\Permission;
use App\Ui\ListFilters;
use App\Ui\Pager;
use App\Ui\Sort;
use App\Warehouse\StockLedger;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/magazyn/dokumenty')]
#[IsGranted(Permission::DOCUMENT_VIEW)]
final class DocumentController extends AbstractController
{
    use ModalResponses;

    public function __construct(
        private readonly StockDocumentRepository $documents,
        private readonly StockLedger $ledger,
        private readonly EntityManagerInterface $em,
        private readonly AuditLogger $audit,
    ) {
    }

    #[Route('', name: 'app_document_index', methods: ['GET'])]
    public function index(Request $request, ListFilters $listFilters, WarehouseRepository $warehouses): Response
    {
        if ($redirect = $listFilters->restore($request, 'documents')) {
            return $redirect;
        }

        $filters = [
            'type' => DocumentType::tryFrom($request->query->getString('typ')),
            'status' => DocumentStatus::tryFrom($request->query->getString('status')),
            'warehouse' => (int) $request->query->getString('magazyn') ?: null,
            'from' => self::date($request->query->getString('od')),
            'to' => self::date($request->query->getString('do')),
            'q' => trim($request->query->getString('q')),
        ];

        $fields = $this->isGranted(Permission::AMOUNTS_VIEW) ? StockDocumentRepository::SORT : array_diff_key(StockDocumentRepository::SORT, ['netto' => true]);
        $sort = Sort::fromRequest($request, $fields, 'data', 'desc', ['netto']);

        return $this->render('warehouse/document/index.html.twig', [
            'pager' => Pager::fromQuery($this->documents->createListQuery($filters, $sort), $request->query->getInt('strona', 1)),
            'filters' => $filters,
            'sort' => $sort,
            'warehouses' => $warehouses->findBy([], ['name' => 'ASC']),
        ]);
    }

    #[Route('/nowy', name: 'app_document_new', methods: ['GET', 'POST'])]
    #[IsGranted(Permission::DOCUMENT_EDIT)]
    public function new(Request $request, #[CurrentUser] User $user, WarehouseRepository $warehouses): Response
    {
        $type = DocumentType::tryFrom($request->query->getString('typ'));
        if (null === $type) {
            return $this->render('warehouse/document/choose.html.twig', ['has_warehouses' => [] !== $warehouses->findBy(['active' => true], [], 1)]);
        }

        $document = (new StockDocument($type))->setCreatedBy($user);
        $active = $warehouses->findBy(['active' => true], ['name' => 'ASC']);
        if (1 === \count($active)) {
            $type->needsSource() ? $document->setSourceWarehouse($active[0]) : $document->setTargetWarehouse($active[0]);
        }

        return $this->handleForm($document, $request);
    }

    #[Route('/{id}', name: 'app_document_show', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function show(StockDocument $document, StockMovementRepository $movements): Response
    {
        return $this->render('warehouse/document/show.html.twig', [
            'document' => $document,
            'warnings' => $document->isDraft() ? $this->ledger->preview($document) : [],
            'movements' => $document->isApproved() ? $movements->findForDocument($document) : [],
            'replaced_by' => $this->documents->findOneBy(['replaces' => $document]),
        ]);
    }

    #[Route('/{id}/edycja', name: 'app_document_edit', methods: ['GET', 'POST'])]
    #[IsGranted(Permission::DOCUMENT_EDIT)]
    public function edit(StockDocument $document, Request $request): Response
    {
        if (!$document->isDraft()) {
            $this->addFlash('warning', 'Zatwierdzonego dokumentu nie można edytować – użyj „Popraw”.');

            return $this->redirectToRoute('app_document_show', ['id' => $document->getId()], Response::HTTP_SEE_OTHER);
        }

        return $this->handleForm($document, $request);
    }

    #[Route('/{id}/zatwierdz', name: 'app_document_approve', methods: ['POST'])]
    #[IsGranted(Permission::DOCUMENT_EDIT)]
    public function approve(StockDocument $document, Request $request, #[CurrentUser] User $user): Response
    {
        $this->assertToken($request);
        if ($document->isDraft()) {
            $warnings = $this->ledger->approve($document, $user);
            $this->addFlash('success', \sprintf('Zatwierdzono dokument %s.', $document->getNumber()));
            foreach ($warnings as $warning) {
                $this->addFlash('warning', $warning);
            }
        }

        return $this->redirectToRoute('app_document_show', ['id' => $document->getId()], Response::HTTP_SEE_OTHER);
    }

    #[Route('/{id}/anuluj', name: 'app_document_cancel', methods: ['GET', 'POST'])]
    #[IsGranted(Permission::DOCUMENT_EDIT)]
    public function cancel(StockDocument $document, Request $request, #[CurrentUser] User $user): Response
    {
        if (!$document->isApproved()) {
            return $this->redirectToRoute('app_document_show', ['id' => $document->getId()], Response::HTTP_SEE_OTHER);
        }

        $error = null;
        if ($request->isMethod('POST')) {
            $this->assertToken($request);
            $reason = trim($request->getPayload()->getString('reason'));
            if ('' === $reason) {
                $error = 'Podaj powód anulowania.';
            } else {
                $this->ledger->cancel($document, mb_substr($reason, 0, 255), $user);
                $this->addFlash('success', \sprintf('Anulowano dokument %s – stany cofnięte.', $document->getNumber()));

                return $this->visit($request, $this->generateUrl('app_document_show', ['id' => $document->getId()]));
            }
        }

        return $this->render('warehouse/document/cancel.html.twig', ['document' => $document, 'error' => $error], new Response(null, null !== $error ? 422 : 200));
    }

    #[Route('/{id}/popraw', name: 'app_document_correct', methods: ['POST'])]
    #[IsGranted(Permission::DOCUMENT_EDIT)]
    public function correct(StockDocument $document, Request $request, #[CurrentUser] User $user): Response
    {
        $this->assertToken($request);
        if (!$document->isApproved()) {
            return $this->redirectToRoute('app_document_show', ['id' => $document->getId()], Response::HTTP_SEE_OTHER);
        }

        $draft = $this->ledger->correct($document, $user);
        $this->addFlash('success', \sprintf('Dokument %s anulowany. Popraw szkic i zatwierdź go ponownie.', $document->getNumber()));

        return $this->redirectToRoute('app_document_edit', ['id' => $draft->getId()], Response::HTTP_SEE_OTHER);
    }

    #[Route('/{id}/usun', name: 'app_document_delete', methods: ['POST'])]
    #[IsGranted(Permission::DOCUMENT_EDIT)]
    public function delete(StockDocument $document, Request $request): Response
    {
        $this->assertToken($request);
        if (!$document->isDraft()) {
            throw $this->createAccessDeniedException('Usunąć można tylko szkic.');
        }

        $this->em->remove($document);
        $this->em->flush();
        $this->addFlash('success', 'Usunięto szkic dokumentu.');

        return $this->redirectToRoute('app_document_index', [], Response::HTTP_SEE_OTHER);
    }

    #[Route('/{id}/pdf', name: 'app_document_pdf', methods: ['GET'])]
    public function pdf(StockDocument $document, PdfRenderer $pdf): Response
    {
        return $pdf->render('warehouse/document/pdf.html.twig', [
            'document' => $document,
            'amounts' => $this->isGranted(Permission::AMOUNTS_VIEW),
            'farm_name' => $document->getFarm()?->getName(),
        ], preg_replace('/[^A-Za-z0-9_-]+/', '-', $document->getLabel()).'.pdf');
    }

    private function handleForm(StockDocument $document, Request $request): Response
    {
        $isNew = null === $document->getId();
        $form = $this->createForm(StockDocumentType::class, $document, ['document_type' => $document->getType()]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            foreach ($document->getItems() as $item) {
                $item->recalculate();
            }
            $this->em->persist($document);
            $this->em->flush();
            $this->audit->log('StockDocument', $document->getId(), $isNew ? 'create' : 'update');
            $this->em->flush();

            if ($request->getPayload()->getBoolean('approve') && $this->isGranted(Permission::DOCUMENT_EDIT)) {
                $user = $this->getUser();
                $warnings = $this->ledger->approve($document, $user instanceof User ? $user : null);
                $this->addFlash('success', \sprintf('Zatwierdzono dokument %s.', $document->getNumber()));
                foreach ($warnings as $warning) {
                    $this->addFlash('warning', $warning);
                }
            } else {
                $this->addFlash('success', 'Zapisano szkic. Stany zmienią się po zatwierdzeniu.');
            }

            return $this->redirectToRoute('app_document_show', ['id' => $document->getId()], Response::HTTP_SEE_OTHER);
        }

        return $this->render('warehouse/document/form.html.twig', ['form' => $form, 'document' => $document], new Response(null, $form->isSubmitted() ? 422 : 200));
    }

    private function assertToken(Request $request): void
    {
        if (!$this->isCsrfTokenValid('stock-document', $request->getPayload()->getString('_token'))) {
            throw $this->createAccessDeniedException();
        }
    }

    private static function date(string $value): ?\DateTimeImmutable
    {
        return 1 === preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) ? new \DateTimeImmutable($value) : null;
    }
}
