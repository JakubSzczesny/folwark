<?php

declare(strict_types=1);

namespace App\Controller;

use App\Analytics\PriceAnalysis;
use App\Entity\Contract;
use App\Entity\Invoice;
use App\Entity\User;
use App\Enum\ContractKind;
use App\Enum\InvoiceKind;
use App\Enum\InvoiceStatus;
use App\Enum\PaymentStatus;
use App\Export\PdfRenderer;
use App\Export\XlsxExporter;
use App\Farm\FarmContext;
use App\Form\CostOnlyType;
use App\Invoice\InvoiceActions;
use App\Invoice\InvoiceFilter;
use App\Invoice\InvoiceXmlService;
use App\Ksef\KsefException;
use App\Message\SyncKsefMetadata;
use App\Repository\AuditLogRepository;
use App\Repository\ContractorRepository;
use App\Repository\ContractRepository;
use App\Repository\CostCategoryRepository;
use App\Repository\InvoiceRepository;
use App\Repository\KsefSyncRepository;
use App\Repository\SavedFilterRepository;
use App\Security\Permission;
use App\Twig\FormatExtension;
use App\Ui\ListFilters;
use App\Ui\Pager;
use App\Ui\RecentChoices;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\UX\Turbo\TurboBundle;

#[Route('/faktury')]
#[IsGranted(Permission::INVOICE_VIEW)]
final class InvoiceController extends AbstractController
{
    private const string LIST_KEY = 'invoices';

    public function __construct(
        private readonly InvoiceRepository $invoices,
        private readonly InvoiceActions $actions,
        private readonly InvoiceXmlService $xml,
        private readonly EntityManagerInterface $em,
        private readonly FarmContext $farmContext,
        private readonly PriceAnalysis $prices,
    ) {
    }

    #[Route('', name: 'app_invoice_index', methods: ['GET'])]
    public function index(
        Request $request,
        ListFilters $listFilters,
        CostCategoryRepository $categories,
        ContractorRepository $contractors,
        KsefSyncRepository $syncs,
    ): Response {
        if ($redirect = $listFilters->restore($request, self::LIST_KEY)) {
            return $redirect;
        }

        $filter = InvoiceFilter::fromRequest($request);
        $pager = Pager::fromQuery($this->invoices->createListQuery($filter), $request->query->getInt('strona', 1));

        return $this->render('invoice/index.html.twig', [
            'filter' => $filter,
            'pager' => $pager,
            'sums' => $this->invoices->sums($filter),
            'new_counts' => [
                'kosztowe' => $this->invoices->countNew(InvoiceKind::Cost),
                'przychodowe' => $this->invoices->countNew(InvoiceKind::Income),
            ],
            'categories' => $categories->findActive(),
            'selected_contractor' => null !== $filter->contractorId ? $contractors->find($filter->contractorId) : null,
            'statuses' => InvoiceStatus::cases(),
            'payments' => PaymentStatus::cases(),
            'last_sync' => $syncs->findLastStarted(),
        ]);
    }

    #[Route('/eksport', name: 'app_invoice_export', methods: ['GET'])]
    #[IsGranted(Permission::EXPORT)]
    public function export(Request $request, XlsxExporter $exporter): Response
    {
        $filter = InvoiceFilter::fromRequest($request);
        $rows = (function () use ($filter): \Generator {
            foreach ($this->invoices->createListQuery($filter)->getQuery()->toIterable() as $invoice) {
                \assert($invoice instanceof Invoice);
                yield [
                    $invoice->getIssueDate(),
                    $invoice->getNumber(),
                    $invoice->getKind()->label(),
                    $invoice->getType()->label(),
                    $invoice->getContractorLabel(),
                    $invoice->getContractorNip(),
                    $invoice->getNet(),
                    $invoice->getVat(),
                    $invoice->getGross(),
                    $invoice->getCurrency(),
                    $invoice->getPaymentDue(),
                    $invoice->getStatus()->label(),
                    $invoice->getPayment()->label(),
                    $invoice->getCostCenter()?->getName(),
                    $invoice->getCostCategory()?->getName(),
                    $invoice->getKsefNumber(),
                ];
                $this->em->detach($invoice);
            }
        })();

        $sums = $this->invoices->sums($filter)['PLN'] ?? null;

        return $exporter->response(
            \sprintf('faktury-%s-%s.xlsx', $filter->tab, date('Y-m-d')),
            'Faktury',
            [
                ['label' => 'Data wystawienia', 'type' => 'date'],
                ['label' => 'Numer', 'width' => 22],
                ['label' => 'Rodzaj', 'width' => 12],
                ['label' => 'Typ', 'width' => 12],
                ['label' => 'Kontrahent', 'width' => 36],
                ['label' => 'NIP', 'width' => 14],
                ['label' => 'Netto', 'type' => 'money'],
                ['label' => 'VAT', 'type' => 'money'],
                ['label' => 'Brutto', 'type' => 'money'],
                ['label' => 'Waluta', 'width' => 8],
                ['label' => 'Termin płatności', 'type' => 'date'],
                ['label' => 'Status', 'width' => 18],
                ['label' => 'Płatność', 'width' => 12],
                ['label' => 'Ośrodek kosztów', 'width' => 20],
                ['label' => 'Kategoria kosztu', 'width' => 20],
                ['label' => 'Numer KSeF', 'width' => 40],
            ],
            $rows,
            null !== $sums ? ['', 'Razem (PLN)', '', '', '', '', $sums['net'], $sums['vat'], $sums['gross']] : null,
        );
    }

    #[Route('/synchronizuj', name: 'app_invoice_sync', methods: ['POST'])]
    #[IsGranted(Permission::INVOICE_MANAGE)]
    public function sync(Request $request, MessageBusInterface $bus, #[CurrentUser] User $user): Response
    {
        $this->assertToken($request);
        $bus->dispatch(new SyncKsefMetadata(true, $user->getId(), $this->farmContext->farmId()));
        $this->addFlash('success', 'Synchronizacja z KSeF została zlecona. Nowe faktury pojawią się za chwilę.');

        return $this->redirectToRoute('pulpit' === $request->query->get('z') ? 'app_dashboard' : 'app_invoice_index', [], Response::HTTP_SEE_OTHER);
    }

    #[Route('/przeczytane', name: 'app_invoice_read_all', methods: ['POST'])]
    #[IsGranted(Permission::INVOICE_MANAGE)]
    public function readAll(Request $request, #[CurrentUser] User $user): Response
    {
        $this->assertToken($request);
        $count = $this->actions->markAllRead($user);
        $this->addFlash('success', \sprintf('Oznaczono jako przeczytane: %d %s.', $count, FormatExtension::plural($count, 'faktura', 'faktury', 'faktur')));

        return $this->redirectToRoute('pulpit' === $request->query->get('z') ? 'app_dashboard' : 'app_invoice_index', [], Response::HTTP_SEE_OTHER);
    }

    #[Route('/{id}/podglad', name: 'app_invoice_preview', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function preview(Invoice $invoice, #[CurrentUser] User $user): Response
    {
        $this->markRead($invoice, $user);

        return $this->render('invoice/_preview.html.twig', $this->previewContext($invoice));
    }

    #[Route('/{id}', name: 'app_invoice_show', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function show(Invoice $invoice, #[CurrentUser] User $user, SavedFilterRepository $savedFilters, AuditLogRepository $logs): Response
    {
        $this->markRead($invoice, $user);

        $saved = $savedFilters->findOneBy(['user' => $user, 'listKey' => self::LIST_KEY]);
        parse_str($saved?->getQuery() ?? '', $query);
        $filter = InvoiceFilter::fromRequest(new Request($query));
        if (null === $filter->kind() || $filter->kind() === $invoice->getKind()) {
            $neighbours = $this->invoices->neighbours($filter, $invoice);
        } else {
            $neighbours = ['prev' => null, 'next' => null];
        }

        return $this->render('invoice/show.html.twig', $this->previewContext($invoice) + [
            'neighbours' => $neighbours,
            'history' => $logs->findForEntity('Invoice', (int) $invoice->getId()),
        ]);
    }

    #[Route('/{id}/pozycje', name: 'app_invoice_fetch_items', methods: ['POST'])]
    public function fetchItems(Invoice $invoice, Request $request): Response
    {
        $this->assertToken($request);

        try {
            $this->xml->fetch($invoice);
            $this->em->flush();
        } catch (KsefException $e) {
            $this->addFlash('error', 'Nie udało się pobrać faktury z KSeF: '.$e->getMessage());
        }

        return $this->afterAction($request, $invoice);
    }

    #[Route('/{id}/pomin', name: 'app_invoice_skip', methods: ['POST'])]
    #[IsGranted(Permission::INVOICE_MANAGE)]
    public function skip(Invoice $invoice, Request $request): Response
    {
        $this->assertToken($request);
        $this->actions->skip($invoice);

        return $this->afterAction($request, $invoice);
    }

    #[Route('/{id}/przywroc', name: 'app_invoice_restore', methods: ['POST'])]
    #[IsGranted(Permission::INVOICE_MANAGE)]
    public function restore(Invoice $invoice, Request $request): Response
    {
        $this->assertToken($request);
        $this->actions->restore($invoice);

        return $this->afterAction($request, $invoice);
    }

    #[Route('/{id}/oplacona', name: 'app_invoice_paid', methods: ['POST'])]
    #[IsGranted(Permission::INVOICE_MANAGE)]
    public function paid(Invoice $invoice, Request $request): Response
    {
        $this->assertToken($request);
        $this->actions->markPaid($invoice);

        return $this->afterAction($request, $invoice);
    }

    #[Route('/{id}/nieoplacona', name: 'app_invoice_unpaid', methods: ['POST'])]
    #[IsGranted(Permission::INVOICE_MANAGE)]
    public function unpaid(Invoice $invoice, Request $request): Response
    {
        $this->assertToken($request);
        $this->actions->markUnpaid($invoice);

        return $this->afterAction($request, $invoice);
    }

    #[Route('/{id}/rodzaj', name: 'app_invoice_kind', methods: ['POST'])]
    #[IsGranted(Permission::INVOICE_MANAGE)]
    public function kind(Invoice $invoice, Request $request): Response
    {
        $this->assertToken($request);
        $this->actions->changeKind($invoice, $invoice->getKind()->opposite());
        $this->addFlash('success', 'Zmieniono klasyfikację na: '.$invoice->getKind()->label().'.');

        return $this->redirectToRoute('app_invoice_show', ['id' => $invoice->getId()], Response::HTTP_SEE_OTHER);
    }

    #[Route('/{id}/uwagi', name: 'app_invoice_notes', methods: ['POST'])]
    #[IsGranted(Permission::INVOICE_MANAGE)]
    public function notes(Invoice $invoice, Request $request): Response
    {
        $this->assertToken($request);
        $notes = trim($request->getPayload()->getString('notes'));
        $this->actions->saveNotes($invoice, '' === $notes ? null : $notes);

        return $this->redirectToRoute('app_invoice_show', ['id' => $invoice->getId()], Response::HTTP_SEE_OTHER);
    }

    #[Route('/{id}/tylko-koszt', name: 'app_invoice_cost_only', methods: ['GET', 'POST'])]
    #[IsGranted(Permission::INVOICE_MANAGE)]
    public function costOnly(Invoice $invoice, Request $request, RecentChoices $recent): Response
    {
        $form = $this->createForm(CostOnlyType::class, [
            'costCenter' => $invoice->getCostCenter(),
            'costCategory' => $invoice->getCostCategory(),
        ], ['action' => $request->getRequestUri()]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $center = $form->get('costCenter')->getData();
            $category = $form->get('costCategory')->getData();
            $recent->remember('cost_center', $center?->getId());
            $recent->remember('cost_category', $category?->getId());
            $this->actions->costOnly($invoice, $center, $category);

            return $this->afterAction($request, $invoice);
        }

        return $this->render('invoice/cost_only.html.twig', ['form' => $form, 'invoice' => $invoice], new Response(null, $form->isSubmitted() ? 422 : 200));
    }

    #[Route('/{id}/umowa', name: 'app_invoice_contract', methods: ['GET', 'POST'])]
    #[IsGranted(Permission::INVOICE_MANAGE)]
    public function contract(Invoice $invoice, Request $request, ContractRepository $contracts): Response
    {
        $contractor = $invoice->getContractor();
        $available = null !== $contractor ? $contracts->findActiveFor($contractor) : [];
        $preferred = ContractKind::forInvoice($invoice->getKind());
        usort($available, static fn (Contract $a, Contract $b): int => [$a->getKind() !== $preferred, $a->getNumber()] <=> [$b->getKind() !== $preferred, $b->getNumber()]);

        if ($request->isMethod('POST')) {
            $this->assertToken($request);
            $id = $request->getPayload()->getInt('contract');
            $selected = null;
            foreach ($available as $contract) {
                if ($contract->getId() === $id) {
                    $selected = $contract;
                }
            }
            $this->actions->assignContract($invoice, $selected);

            return $this->afterAction($request, $invoice);
        }

        return $this->render('invoice/contract.html.twig', ['invoice' => $invoice, 'contracts' => $available]);
    }

    #[Route('/{id}/xml', name: 'app_invoice_xml', methods: ['GET'])]
    public function downloadXml(Invoice $invoice): Response
    {
        try {
            if (!$invoice->hasXml()) {
                $this->xml->fetch($invoice);
                $this->em->flush();
            }
        } catch (KsefException $e) {
            $this->addFlash('error', 'Nie udało się pobrać faktury z KSeF: '.$e->getMessage());

            return $this->redirectToRoute('app_invoice_show', ['id' => $invoice->getId()]);
        }

        $response = new Response((string) $this->xml->raw($invoice));
        $response->headers->set('Content-Type', 'application/xml; charset=UTF-8');
        $response->headers->set('Content-Disposition', 'attachment; filename="'.$invoice->getKsefNumber().'.xml"');

        return $response;
    }

    #[Route('/{id}/pdf', name: 'app_invoice_pdf', methods: ['GET'])]
    public function pdf(Invoice $invoice, PdfRenderer $pdf): Response
    {
        return $pdf->render('invoice/pdf.html.twig', $this->previewContext($invoice), 'faktura-'.preg_replace('/[^A-Za-z0-9_-]+/', '-', $invoice->getNumber()).'.pdf');
    }

    /**
     * @return array<string, mixed>
     */
    private function previewContext(Invoice $invoice): array
    {
        $parsed = null;
        $parseError = null;
        try {
            $parsed = $this->xml->get($invoice);
        } catch (\Throwable $e) {
            $parseError = $e->getMessage();
        }

        return [
            'invoice' => $invoice,
            'parsed' => $parsed,
            'parse_error' => $parseError,
            'price_alerts' => $this->isGranted(Permission::AMOUNTS_VIEW) ? $this->prices->alertsForInvoice($invoice) : [],
        ];
    }

    private function markRead(Invoice $invoice, User $user): void
    {
        if ($this->isGranted(Permission::INVOICE_MANAGE)) {
            $this->actions->markRead($invoice, $user);
        }
    }

    /**
     * Akcja z panelu podglądu odświeża panel i wiersz listy (Turbo Stream), z pełnego widoku – całą stronę.
     */
    private function afterAction(Request $request, Invoice $invoice): Response
    {
        $fromPreview = 'preview' === $request->headers->get('Turbo-Frame') || 'podglad' === $request->query->get('z');

        if ($fromPreview && TurboBundle::STREAM_FORMAT === $request->getPreferredFormat()) {
            $request->setRequestFormat(TurboBundle::STREAM_FORMAT);

            return $this->render('invoice/_preview_update.stream.html.twig', $this->previewContext($invoice));
        }

        if ($fromPreview) {
            return $this->redirectToRoute('app_invoice_preview', ['id' => $invoice->getId()], Response::HTTP_SEE_OTHER);
        }

        if (TurboBundle::STREAM_FORMAT === $request->getPreferredFormat() && 'modal' === $request->headers->get('Turbo-Frame')) {
            $request->setRequestFormat(TurboBundle::STREAM_FORMAT);

            return $this->render('_partials/refresh.stream.html.twig');
        }

        if ('pulpit' === $request->query->get('z')) {
            return $this->redirectToRoute('app_dashboard', [], Response::HTTP_SEE_OTHER);
        }

        // Akcja z menu pod prawym przyciskiem na liście – wracamy na tę samą listę (z filtrami).
        if ('lista' === $request->query->get('z')) {
            $referer = (string) $request->headers->get('referer');
            $query = (string) parse_url($referer, \PHP_URL_QUERY);
            $url = $this->generateUrl('app_invoice_index');

            $sameList = parse_url($referer, \PHP_URL_HOST) === $request->getHost() && '/faktury' === parse_url($referer, \PHP_URL_PATH);

            return $this->redirect($sameList && '' !== $query ? $url.'?'.$query : $url, Response::HTTP_SEE_OTHER);
        }

        return $this->redirectToRoute('app_invoice_show', ['id' => $invoice->getId()], Response::HTTP_SEE_OTHER);
    }

    private function assertToken(Request $request): void
    {
        if (!$this->isCsrfTokenValid('invoice', $request->getPayload()->getString('_token'))) {
            throw $this->createAccessDeniedException('Nieprawidłowy token formularza.');
        }
    }
}
