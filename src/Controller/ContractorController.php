<?php

declare(strict_types=1);

namespace App\Controller;

use App\Contractor\ContractorDataUpdater;
use App\Contractor\WhiteListEnricher;
use App\Entity\Contractor;
use App\Entity\ContractorHistory;
use App\Entity\User;
use App\Enum\ContractorKind;
use App\Enum\DataSource;
use App\Enum\InvoiceKind;
use App\Form\ContractorType;
use App\Repository\ContractorHistoryRepository;
use App\Repository\ContractorRepository;
use App\Repository\ContractRepository;
use App\Repository\InvoiceRepository;
use App\Security\Permission;
use App\Ui\ListFilters;
use App\Ui\Pager;
use App\WhiteList\WhiteListException;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/kontrahenci')]
#[IsGranted(Permission::CONTRACTOR_VIEW)]
final class ContractorController extends AbstractController
{
    private const array EDITABLE_FIELDS = ['name', 'shortName', 'nip', 'street', 'postalCode', 'city', 'country', 'email', 'phone', 'contactPerson'];
    private const array TABS = ['faktury', 'zakupy', 'umowy', 'historia', 'notatki'];

    public function __construct(
        private readonly ContractorRepository $contractors,
        private readonly EntityManagerInterface $em,
        private readonly ContractorDataUpdater $updater,
    ) {
    }

    #[Route('', name: 'app_contractor_index', methods: ['GET'])]
    public function index(Request $request, ListFilters $listFilters, InvoiceRepository $invoices, ContractRepository $contracts): Response
    {
        if ($redirect = $listFilters->restore($request, 'contractors')) {
            return $redirect;
        }

        $text = trim($request->query->getString('szukaj')) ?: null;
        $kind = ContractorKind::tryFrom($request->query->getString('typ'));
        $inactive = $request->query->getBoolean('nieaktywni');
        $pager = Pager::fromQuery($this->contractors->createListQuery($text, $kind, $inactive), $request->query->getInt('strona', 1));

        $ids = array_map(static fn (Contractor $c): int => (int) $c->getId(), $pager->items);
        $year = (int) date('Y');

        return $this->render('contractor/index.html.twig', [
            'pager' => $pager,
            'text' => $text,
            'kind' => $kind,
            'inactive' => $inactive,
            'kinds' => [ContractorKind::Supplier, ContractorKind::Customer],
            'totals' => $invoices->yearlyTotalsByContractor($ids, $year),
            'contract_counts' => $contracts->countActiveByContractor($ids),
            'year' => $year,
        ]);
    }

    #[Route('/nowy', name: 'app_contractor_new', methods: ['GET', 'POST'])]
    #[IsGranted(Permission::CONTRACTOR_EDIT)]
    public function new(Request $request): Response
    {
        $contractor = (new Contractor())->setDataSource(DataSource::Manual);
        $form = $this->createForm(ContractorType::class, $contractor, ['allow_nip' => true]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->em->persist($contractor);
            $this->em->flush();
            $this->addFlash('success', 'Dodano kontrahenta.');

            return $this->redirectToRoute('app_contractor_show', ['id' => $contractor->getId()], Response::HTTP_SEE_OTHER);
        }

        return $this->render('contractor/form.html.twig', ['form' => $form, 'contractor' => $contractor]);
    }

    #[Route('/{id}', name: 'app_contractor_show', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function show(
        Contractor $contractor,
        Request $request,
        InvoiceRepository $invoices,
        ContractRepository $contracts,
        ContractorHistoryRepository $history,
    ): Response {
        $tab = \in_array($tab = $request->query->getString('zakladka', 'faktury'), self::TABS, true) ? $tab : 'faktury';
        $years = $invoices->yearsForContractor($contractor);
        $year = $request->query->getInt('rok') ?: ($years[0] ?? (int) date('Y'));

        $context = ['contractor' => $contractor, 'tab' => $tab, 'year' => $year, 'years' => $years];

        if ('faktury' === $tab && $this->isGranted(Permission::INVOICE_VIEW)) {
            $context['cost_invoices'] = $invoices->findForContractor($contractor, InvoiceKind::Cost, $year);
            $context['income_invoices'] = $invoices->findForContractor($contractor, InvoiceKind::Income, $year);
        }
        if ('umowy' === $tab) {
            $context['contracts'] = $contracts->createListQuery(['contractor' => $contractor->getId()])->getQuery()->getResult();
        }
        if ('historia' === $tab) {
            $context['history'] = $history->findBy(['contractor' => $contractor], ['changedAt' => 'DESC', 'id' => 'DESC'], 200);
        }

        return $this->render('contractor/show.html.twig', $context);
    }

    #[Route('/{id}/edycja', name: 'app_contractor_edit', methods: ['GET', 'POST'])]
    #[IsGranted(Permission::CONTRACTOR_EDIT)]
    public function edit(Contractor $contractor, Request $request, #[CurrentUser] User $user): Response
    {
        $before = $this->updater->snapshot($contractor, self::EDITABLE_FIELDS);
        $form = $this->createForm(ContractorType::class, $contractor, ['allow_nip' => null === $before['nip']]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            foreach ($this->updater->recordManualEdit($contractor, $before, $user) as $entry) {
                $this->em->persist($entry);
            }
            $this->em->flush();
            $this->addFlash('success', $contractor->isUpdateLocked() ? 'Zapisano. Dane nie będą nadpisywane z faktur (blokada).' : 'Zapisano zmiany.');

            return $this->redirectToRoute('app_contractor_show', ['id' => $contractor->getId()], Response::HTTP_SEE_OTHER);
        }

        return $this->render('contractor/form.html.twig', ['form' => $form, 'contractor' => $contractor]);
    }

    #[Route('/{id}/odblokuj', name: 'app_contractor_unlock', methods: ['POST'])]
    #[IsGranted(Permission::CONTRACTOR_EDIT)]
    public function unlock(Contractor $contractor, Request $request, #[CurrentUser] User $user): Response
    {
        $this->assertToken($request);
        if ($contractor->isUpdateLocked()) {
            $contractor->setUpdateLocked(false);
            $this->em->persist(new ContractorHistory($contractor, 'updateLocked', 'tak', 'nie', DataSource::Manual, null, $user));
            $this->em->flush();
            $this->addFlash('success', 'Zdjęto blokadę. Dane będą aktualizowane z najnowszych faktur.');
        }

        return $this->redirectToRoute('app_contractor_show', ['id' => $contractor->getId()], Response::HTTP_SEE_OTHER);
    }

    #[Route('/{id}/biala-lista', name: 'app_contractor_white_list', methods: ['POST'])]
    #[IsGranted(Permission::CONTRACTOR_EDIT)]
    public function whiteList(Contractor $contractor, Request $request, WhiteListEnricher $enricher): Response
    {
        $this->assertToken($request);

        try {
            $found = $enricher->enrich($contractor);
            $this->em->flush();
            $this->addFlash($found ? 'success' : 'warning', $found ? 'Sprawdzono na Białej liście VAT.' : 'Podmiotu nie ma w Wykazie podatników VAT.');
        } catch (WhiteListException $e) {
            $this->addFlash('error', $e->getMessage());
        }

        return $this->redirectToRoute('app_contractor_show', ['id' => $contractor->getId()], Response::HTTP_SEE_OTHER);
    }

    #[Route('/{id}/notatki', name: 'app_contractor_notes', methods: ['POST'])]
    #[IsGranted(Permission::CONTRACTOR_EDIT)]
    public function notes(Contractor $contractor, Request $request): Response
    {
        $this->assertToken($request);
        $notes = trim($request->getPayload()->getString('notes'));
        $contractor->setNotes('' === $notes ? null : $notes);
        $this->em->flush();
        $this->addFlash('success', 'Zapisano notatki.');

        return $this->redirectToRoute('app_contractor_show', ['id' => $contractor->getId(), 'zakladka' => 'notatki'], Response::HTTP_SEE_OTHER);
    }

    private function assertToken(Request $request): void
    {
        if (!$this->isCsrfTokenValid('contractor', $request->getPayload()->getString('_token'))) {
            throw $this->createAccessDeniedException('Nieprawidłowy token formularza.');
        }
    }
}
