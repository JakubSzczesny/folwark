<?php

declare(strict_types=1);

namespace App\Controller;

use App\Audit\AuditLogger;
use App\Contract\ContractRealization;
use App\Entity\Contract;
use App\Entity\ContractAttachment;
use App\Enum\ContractKind;
use App\Enum\ContractStatus;
use App\Form\ContractType;
use App\Form\Field\ContractorAutocompleteField;
use App\Repository\ContractorRepository;
use App\Repository\ContractRepository;
use App\Repository\InvoiceRepository;
use App\Security\Permission;
use App\Settings\SettingKey;
use App\Settings\Settings;
use App\Storage\FileStorage;
use App\Ui\ListFilters;
use App\Ui\Pager;
use App\Ui\QueryParam;
use App\Ui\RecentChoices;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/umowy')]
#[IsGranted(Permission::CONTRACT_VIEW)]
final class ContractController extends AbstractController
{
    public function __construct(
        private readonly ContractRepository $contracts,
        private readonly EntityManagerInterface $em,
        private readonly FileStorage $storage,
        private readonly AuditLogger $audit,
    ) {
    }

    #[Route('', name: 'app_contract_index', methods: ['GET'])]
    public function index(Request $request, ListFilters $listFilters, ContractorRepository $contractors, ContractRealization $realization, Settings $settings): Response
    {
        if ($redirect = $listFilters->restore($request, 'contracts')) {
            return $redirect;
        }

        $filters = [
            'contractor' => QueryParam::id($request->query, 'kontrahent'),
            'kind' => ContractKind::tryFrom($request->query->getString('rodzaj')),
            'status' => ContractStatus::tryFrom($request->query->getString('status')),
            'expiring' => $request->query->getBoolean('wygasajace'),
            'days' => $settings->int(SettingKey::NOTIFY_CONTRACT_DAYS),
        ];
        $pager = Pager::fromQuery($this->contracts->createListQuery($filters), $request->query->getInt('strona', 1));

        $progress = [];
        foreach ($pager->items as $contract) {
            $progress[$contract->getId()] = $realization->calculate($contract);
        }

        return $this->render('contract/index.html.twig', [
            'pager' => $pager,
            'filters' => $filters,
            'progress' => $progress,
            'selected_contractor' => null !== $filters['contractor'] ? $contractors->find($filters['contractor']) : null,
        ]);
    }

    #[Route('/nowa', name: 'app_contract_new', methods: ['GET', 'POST'])]
    #[IsGranted(Permission::CONTRACT_EDIT)]
    public function new(Request $request, ContractorRepository $contractors, RecentChoices $recent): Response
    {
        $contract = new Contract();
        if ($id = QueryParam::id($request->query, 'kontrahent')) {
            $contract->setContractor($contractors->find($id));
        }

        return $this->handleForm($contract, $request, $recent);
    }

    #[Route('/{id}', name: 'app_contract_show', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function show(Contract $contract, ContractRealization $realization, InvoiceRepository $invoices): Response
    {
        return $this->render('contract/show.html.twig', [
            'contract' => $contract,
            'realization' => $realization->calculate($contract),
            'invoices' => $invoices->findBy(['contract' => $contract], ['issueDate' => 'DESC']),
        ]);
    }

    #[Route('/{id}/edycja', name: 'app_contract_edit', methods: ['GET', 'POST'])]
    #[IsGranted(Permission::CONTRACT_EDIT)]
    public function edit(Contract $contract, Request $request, RecentChoices $recent): Response
    {
        return $this->handleForm($contract, $request, $recent);
    }

    #[Route('/zalacznik/{id}', name: 'app_contract_attachment', methods: ['GET'])]
    public function attachment(ContractAttachment $attachment): Response
    {
        return $this->storage->response($attachment->getPath(), $attachment->getName(), true);
    }

    #[Route('/zalacznik/{id}/usun', name: 'app_contract_attachment_delete', methods: ['POST'])]
    #[IsGranted(Permission::CONTRACT_EDIT)]
    public function deleteAttachment(ContractAttachment $attachment, Request $request): Response
    {
        if (!$this->isCsrfTokenValid('contract', $request->getPayload()->getString('_token'))) {
            throw $this->createAccessDeniedException();
        }

        $contract = $attachment->getContract();
        $contract->removeAttachment($attachment);
        $this->storage->delete($attachment->getPath());
        $this->em->flush();

        return $this->redirectToRoute('app_contract_show', ['id' => $contract->getId()], Response::HTTP_SEE_OTHER);
    }

    private function handleForm(Contract $contract, Request $request, RecentChoices $recent): Response
    {
        $isNew = null === $contract->getId();
        $form = $this->createForm(ContractType::class, $contract);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->em->persist($contract);
            $this->em->flush();
            $this->storeAttachments($form, $contract);
            $recent->remember(ContractorAutocompleteField::RECENT_KIND, $contract->getContractor()?->getId());
            $this->audit->log('Contract', $contract->getId(), $isNew ? 'create' : 'update');
            $this->em->flush();
            $this->addFlash('success', $isNew ? 'Dodano umowę.' : 'Zapisano umowę.');

            return $this->redirectToRoute('app_contract_show', ['id' => $contract->getId()], Response::HTTP_SEE_OTHER);
        }

        return $this->render('contract/form.html.twig', ['form' => $form, 'contract' => $contract]);
    }

    /**
     * @param FormInterface<Contract> $form
     */
    private function storeAttachments(FormInterface $form, Contract $contract): void
    {
        foreach ((array) $form->get('newAttachments')->getData() as $file) {
            if (!$file instanceof UploadedFile) {
                continue;
            }
            $farm = $contract->getFarm() ?? throw new \LogicException('Umowa bez gospodarstwa.');
            $path = $this->storage->storeUpload($file, FileStorage::farmDirectory($farm).'/contracts/'.$contract->getId());
            $contract->addAttachment(new ContractAttachment($contract, $path, $file->getClientOriginalName()));
        }
    }
}
