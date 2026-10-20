<?php

declare(strict_types=1);

namespace App\Controller\Machine;

use App\Entity\Machine;
use App\Entity\User;
use App\Form\MachineType;
use App\Machine\MachineCosts;
use App\Machine\MachineInfo;
use App\Machine\MachineManager;
use App\Repository\MachineCategoryRepository;
use App\Repository\MachineRepository;
use App\Repository\MachineServiceRepository;
use App\Security\Permission;
use App\Settings\SettingKey;
use App\Settings\Settings;
use App\Storage\Thumbnails;
use App\Ui\ListFilters;
use App\Ui\Pager;
use App\Ui\QueryParam;
use App\Ui\Sort;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/maszyny')]
#[IsGranted(Permission::MACHINE_VIEW)]
final class MachineController extends AbstractController
{
    public function __construct(
        private readonly MachineRepository $machines,
        private readonly MachineServiceRepository $services,
        private readonly MachineManager $manager,
    ) {
    }

    #[Route('', name: 'app_machine_index', methods: ['GET'])]
    public function index(Request $request, ListFilters $listFilters, MachineCategoryRepository $categories, Settings $settings): Response
    {
        if ($redirect = $listFilters->restore($request, 'machines')) {
            return $redirect;
        }

        $days = $settings->int(SettingKey::ATTENTION_DAYS);
        $filters = [
            'q' => trim($request->query->getString('szukaj')) ?: null,
            'category' => (int) $request->query->getString('kategoria') ?: null,
            'deadlines' => $request->query->getBoolean('terminy'),
            'inactive' => $request->query->getBoolean('wycofane'),
            'until' => new \DateTimeImmutable(\sprintf('today +%d days', $days)),
        ];
        $sort = Sort::fromRequest($request, MachineRepository::SORT, 'nazwa', 'asc');
        $pager = Pager::fromQuery($this->machines->createListQuery($filters, $sort), $request->query->getInt('strona', 1));
        $ids = array_map(static fn (Machine $m): int => (int) $m->getId(), $pager->items);

        return $this->render('machine/index.html.twig', [
            'pager' => $pager,
            'filters' => $filters,
            'days' => $days,
            'sort' => $sort,
            'categories' => $categories->findOrdered(),
            'counts' => $this->machines->countByCategory($filters['inactive']),
            'last_services' => $this->machines->lastServiceDates($ids),
        ]);
    }

    #[Route('/nowa', name: 'app_machine_new', methods: ['GET', 'POST'])]
    #[IsGranted(Permission::MACHINE_MANAGE)]
    public function new(Request $request, #[CurrentUser] User $user, MachineCategoryRepository $categories): Response
    {
        $machine = new Machine();
        if ($id = (int) $request->query->getString('kategoria')) {
            $machine->setCategory($categories->find($id));
        }

        return $this->handleForm($machine, $request, $user);
    }

    #[Route('/{id}', name: 'app_machine_show', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function show(Machine $machine, Request $request, MachineInfo $info): Response
    {
        $showAll = $request->query->getBoolean('wszystkie');
        $tree = $info->tree($machine, (int) $request->query->getString('kategoria') ?: null, $showAll);

        return $this->renderTab($machine, 'info', [
            'tree' => $tree,
            'show_all' => $showAll,
            'sections' => null !== $tree['selected'] ? $info->sections($machine, $tree['selected']) : [],
        ]);
    }

    #[Route('/{id}/serwis', name: 'app_machine_service', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function service(Machine $machine): Response
    {
        return $this->renderTab($machine, 'service', ['services' => $this->services->findForMachine($machine)]);
    }

    #[Route('/{id}/notatki', name: 'app_machine_notes', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function notes(Machine $machine): Response
    {
        return $this->renderTab($machine, 'notes');
    }

    #[Route('/{id}/koszty', name: 'app_machine_costs', requirements: ['id' => '\d+'], methods: ['GET'])]
    #[IsGranted(Permission::AMOUNTS_VIEW)]
    public function costs(Machine $machine, Request $request, MachineCosts $costs): Response
    {
        $rows = $costs->rows($machine);
        $totals = MachineCosts::yearlyTotals($rows);
        $year = QueryParam::id($request->query, 'rok') ?? (array_key_first($totals) ?? (int) date('Y'));

        return $this->renderTab($machine, 'costs', [
            'totals' => $totals,
            'year' => $year,
            'rows' => array_values(array_filter($rows, static fn (array $row): bool => (int) $row['date']->format('Y') === $year)),
        ]);
    }

    #[Route('/{id}/edycja', name: 'app_machine_edit', requirements: ['id' => '\d+'], methods: ['GET', 'POST'])]
    #[IsGranted(Permission::MACHINE_MANAGE)]
    public function edit(Machine $machine, Request $request, #[CurrentUser] User $user): Response
    {
        return $this->handleForm($machine, $request, $user);
    }

    #[Route('/{id}/usun', name: 'app_machine_delete', requirements: ['id' => '\d+'], methods: ['POST'])]
    #[IsGranted(Permission::MACHINE_MANAGE)]
    public function delete(Machine $machine, Request $request): Response
    {
        if (!$this->isCsrfTokenValid('machine-delete', $request->getPayload()->getString('_token'))) {
            throw $this->createAccessDeniedException();
        }

        $name = $machine->getName();
        $this->manager->delete($machine);
        $this->addFlash('success', \sprintf('Usunięto maszynę „%s”.', $name));

        return $this->redirectToRoute('app_machine_index', [], Response::HTTP_SEE_OTHER);
    }

    #[Route('/{id}/zdjecie/{filter}', name: 'app_machine_photo', requirements: ['id' => '\d+', 'filter' => 'thumb|card|photo'], methods: ['GET'])]
    public function photo(Machine $machine, string $filter, Thumbnails $thumbnails): Response
    {
        $path = $machine->getPhotoPath() ?? throw $this->createNotFoundException();

        return $thumbnails->response($path, $filter);
    }

    #[Route('/{id}/tara', name: 'app_machine_tare', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function tare(Machine $machine): Response
    {
        return $this->render('machine/tare.html.twig', ['machine' => $machine]);
    }

    /**
     * @param array<string, mixed> $context
     */
    private function renderTab(Machine $machine, string $tab, array $context = []): Response
    {
        return $this->render('machine/show.html.twig', $context + [
            'machine' => $machine,
            'tab' => $tab,
            'plans' => $this->services->findOpenPlans($machine),
            'last_service' => $this->services->findLastForMachine($machine),
        ]);
    }

    private function handleForm(Machine $machine, Request $request, User $user): Response
    {
        $isNew = null === $machine->getId();
        $form = $this->createForm(MachineType::class, $machine);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $photo = $form->get('photo')->getData();
            $this->manager->save(
                $machine,
                $form->get('tareKg')->getData(),
                $photo instanceof UploadedFile ? $photo : null,
                $form->has('removePhoto') && true === $form->get('removePhoto')->getData(),
                $user,
            );
            $this->addFlash('success', $isNew ? 'Dodano maszynę.' : 'Zapisano zmiany.');

            return $this->redirectToRoute('app_machine_show', ['id' => $machine->getId()], Response::HTTP_SEE_OTHER);
        }

        return $this->render('machine/form.html.twig', ['form' => $form, 'machine' => $machine], new Response(null, $form->isSubmitted() ? 422 : 200));
    }
}
