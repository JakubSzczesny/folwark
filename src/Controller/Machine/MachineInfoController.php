<?php

declare(strict_types=1);

namespace App\Controller\Machine;

use App\Controller\ModalResponses;
use App\Entity\Machine;
use App\Entity\MachineInfoAttachment;
use App\Entity\MachineInfoCategory;
use App\Entity\MachineInfoEntry;
use App\Form\MachineInfoEntryType;
use App\Machine\MachineInfo;
use App\Repository\MachineInfoCategoryRepository;
use App\Repository\MachineRepository;
use App\Security\Permission;
use App\Storage\FileStorage;
use App\Storage\Thumbnails;
use App\Twig\FormatExtension;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Zakładka „Informacje” karty maszyny: kategorie, wpisy, załączniki, kopiowanie z innej maszyny.
 */
#[Route('/maszyny')]
#[IsGranted(Permission::MACHINE_VIEW)]
final class MachineInfoController extends AbstractController
{
    use ModalResponses;

    public function __construct(
        private readonly MachineInfo $info,
        private readonly MachineInfoCategoryRepository $categories,
        private readonly EntityManagerInterface $em,
    ) {
    }

    #[Route('/{id}/informacje/kategoria', name: 'app_machine_info_category_new', requirements: ['id' => '\d+'], methods: ['GET', 'POST'])]
    #[IsGranted(Permission::MACHINE_EDIT)]
    public function newCategory(Machine $machine, Request $request): Response
    {
        $parent = (int) $request->query->getString('rodzic') ? $this->categories->find((int) $request->query->getString('rodzic')) : null;
        $error = null;

        if ($request->isMethod('POST')) {
            $this->assertToken($request);
            $name = trim($request->getPayload()->getString('name'));
            if ('' === $name || mb_strlen($name) > 80) {
                $error = 'Podaj nazwę (do 80 znaków).';
            } else {
                $category = $this->info->addCategory($name, $parent);

                return $this->visit($request, $this->generateUrl('app_machine_show', ['id' => $machine->getId(), 'kategoria' => $category->getId()]));
            }
        }

        return $this->render('machine/info/category.html.twig', [
            'machine' => $machine,
            'parent' => $parent,
            'category' => null,
            'error' => $error,
        ], new Response(null, null !== $error ? 422 : 200));
    }

    #[Route('/{id}/informacje/kategoria/{category}', name: 'app_machine_info_category_edit', requirements: ['id' => '\d+', 'category' => '\d+'], methods: ['GET', 'POST'])]
    #[IsGranted(Permission::MACHINE_EDIT)]
    public function editCategory(Machine $machine, MachineInfoCategory $category, Request $request): Response
    {
        $error = null;

        if ($request->isMethod('POST')) {
            $this->assertToken($request);
            $name = trim($request->getPayload()->getString('name'));
            if ('' === $name || mb_strlen($name) > 80) {
                $error = 'Podaj nazwę (do 80 znaków).';
            } else {
                $category->setName($name);
                $this->em->flush();

                return $this->visit($request, $this->generateUrl('app_machine_show', ['id' => $machine->getId(), 'kategoria' => $category->getId()]));
            }
        }

        return $this->render('machine/info/category.html.twig', [
            'machine' => $machine,
            'parent' => $category->getParent(),
            'category' => $category,
            'error' => $error,
            'can_delete' => !$this->categories->isUsed($category),
        ], new Response(null, null !== $error ? 422 : 200));
    }

    #[Route('/{id}/informacje/kategoria/{category}/usun', name: 'app_machine_info_category_delete', requirements: ['id' => '\d+', 'category' => '\d+'], methods: ['POST'])]
    #[IsGranted(Permission::MACHINE_EDIT)]
    public function deleteCategory(Machine $machine, MachineInfoCategory $category, Request $request): Response
    {
        $this->assertToken($request);
        $parent = $category->getParent();

        if ($this->info->deleteCategory($category)) {
            $this->addFlash('success', \sprintf('Usunięto kategorię „%s”.', $category->getName()));
        } else {
            $this->addFlash('error', 'Kategorii z wpisami albo podkategoriami nie można usunąć.');
        }

        return $this->visit($request, $this->generateUrl('app_machine_show', ['id' => $machine->getId(), 'kategoria' => $parent?->getId()]));
    }

    #[Route('/informacje/kategorie/kolejnosc', name: 'app_machine_info_category_order', methods: ['POST'])]
    #[IsGranted(Permission::MACHINE_EDIT)]
    public function reorder(Request $request): Response
    {
        if (!$this->isCsrfTokenValid('machine-info', $request->headers->get('X-CSRF-Token', ''))) {
            throw $this->createAccessDeniedException();
        }

        $payload = $request->toArray();
        $parent = isset($payload['parent']) && is_numeric($payload['parent']) ? $this->categories->find((int) $payload['parent']) : null;
        $ids = array_values(array_map('intval', array_filter((array) ($payload['ids'] ?? []), 'is_numeric')));
        $this->info->reorder($parent, $ids);

        return new JsonResponse(['ok' => true]);
    }

    #[Route('/{id}/informacje/wpis', name: 'app_machine_info_entry_new', requirements: ['id' => '\d+'], methods: ['GET', 'POST'])]
    #[IsGranted(Permission::MACHINE_EDIT)]
    public function newEntry(Machine $machine, Request $request): Response
    {
        $category = ((int) $request->query->getString('kategoria') ? $this->categories->find((int) $request->query->getString('kategoria')) : null)
            ?? $this->categories->findAllOrdered()[0]
            ?? throw $this->createNotFoundException('Najpierw dodaj kategorię informacji.');

        return $this->handleEntry(new MachineInfoEntry($machine, $category), $request);
    }

    #[Route('/informacje/wpis/{id}', name: 'app_machine_info_entry_edit', requirements: ['id' => '\d+'], methods: ['GET', 'POST'])]
    #[IsGranted(Permission::MACHINE_EDIT)]
    public function editEntry(MachineInfoEntry $entry, Request $request): Response
    {
        return $this->handleEntry($entry, $request);
    }

    #[Route('/informacje/wpis/{id}/usun', name: 'app_machine_info_entry_delete', requirements: ['id' => '\d+'], methods: ['POST'])]
    #[IsGranted(Permission::MACHINE_EDIT)]
    public function deleteEntry(MachineInfoEntry $entry, Request $request): Response
    {
        $this->assertToken($request);
        $url = $this->entryUrl($entry);
        $this->info->deleteEntry($entry);

        return $this->visit($request, $url);
    }

    #[Route('/informacje/zalacznik/{id}', name: 'app_machine_info_attachment', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function attachment(MachineInfoAttachment $attachment, FileStorage $storage): Response
    {
        if ($attachment->isLink()) {
            return $this->redirect($attachment->getLocation());
        }

        return $storage->response($attachment->getLocation(), $attachment->getLabel(), true);
    }

    #[Route('/informacje/zalacznik/{id}/{filter}', name: 'app_machine_info_thumbnail', requirements: ['id' => '\d+', 'filter' => 'thumb|card|photo'], methods: ['GET'])]
    public function thumbnail(MachineInfoAttachment $attachment, string $filter, Thumbnails $thumbnails): Response
    {
        if (!$attachment->isPhoto()) {
            throw $this->createNotFoundException();
        }

        return $thumbnails->response($attachment->getLocation(), $filter);
    }

    #[Route('/informacje/zalacznik/{id}/usun', name: 'app_machine_info_attachment_delete', requirements: ['id' => '\d+'], methods: ['POST'])]
    #[IsGranted(Permission::MACHINE_EDIT)]
    public function deleteAttachment(MachineInfoAttachment $attachment, Request $request): Response
    {
        $this->assertToken($request);
        $entry = $attachment->getEntry();
        $this->info->deleteAttachment($attachment);

        return $this->redirectToRoute('app_machine_info_entry_edit', ['id' => $entry->getId()], Response::HTTP_SEE_OTHER);
    }

    #[Route('/{id}/informacje/kopiuj', name: 'app_machine_info_copy', requirements: ['id' => '\d+'], methods: ['GET', 'POST'])]
    #[IsGranted(Permission::MACHINE_EDIT)]
    public function copy(Machine $machine, Request $request, MachineRepository $machines): Response
    {
        $counts = $machines->countInfoEntries();
        $sources = array_values(array_filter($machines->findBy([], ['name' => 'ASC']), static fn (Machine $m): bool => $m !== $machine && ($counts[$m->getId()] ?? 0) > 0));
        $roots = $this->categories->findBy(['parent' => null], ['position' => 'ASC', 'id' => 'ASC']);
        $error = null;

        if ($request->isMethod('POST')) {
            $this->assertToken($request);
            $payload = $request->getPayload();
            $source = $machines->find((int) $payload->getString('source'));
            $selected = array_values(array_filter($roots, static fn (MachineInfoCategory $c): bool => \in_array((string) $c->getId(), $payload->all('categories'), true)));

            if (null === $source || $source === $machine) {
                $error = 'Wybierz maszynę, z której skopiować informacje.';
            } elseif ([] === $selected) {
                $error = 'Zaznacz co najmniej jedną kategorię.';
            } else {
                $copied = $this->info->copy($source, $machine, $selected);
                $this->addFlash('success', 0 === $copied
                    ? 'Nie było czego kopiować – te wpisy już są na tej maszynie.'
                    : \sprintf('Skopiowano %d %s z maszyny „%s”.', $copied, FormatExtension::plural($copied, 'wpis', 'wpisy', 'wpisów'), $source->getName()));

                return $this->visit($request, $this->generateUrl('app_machine_show', ['id' => $machine->getId()]));
            }
        }

        return $this->render('machine/info/copy.html.twig', [
            'machine' => $machine,
            'sources' => $sources,
            'counts' => $counts,
            'roots' => $roots,
            'error' => $error,
        ], new Response(null, null !== $error ? 422 : 200));
    }

    private function handleEntry(MachineInfoEntry $entry, Request $request): Response
    {
        $form = $this->createForm(MachineInfoEntryType::class, $entry, ['action' => $request->getRequestUri()]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $files = array_values(array_filter((array) $form->get('files')->getData(), static fn (mixed $f): bool => $f instanceof UploadedFile));
            $this->info->saveEntry($entry, $files, (string) $form->get('links')->getData());

            return $this->visit($request, $this->entryUrl($entry));
        }

        return $this->render('machine/info/entry.html.twig', ['form' => $form, 'entry' => $entry], new Response(null, $form->isSubmitted() ? 422 : 200));
    }

    private function entryUrl(MachineInfoEntry $entry): string
    {
        return $this->generateUrl('app_machine_show', ['id' => $entry->getMachine()->getId(), 'kategoria' => $entry->getCategory()->getId()]);
    }

    private function assertToken(Request $request): void
    {
        if (!$this->isCsrfTokenValid('machine-info', $request->getPayload()->getString('_token'))) {
            throw $this->createAccessDeniedException();
        }
    }
}
