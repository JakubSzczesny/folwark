<?php

declare(strict_types=1);

namespace App\Controller\Product;

use App\Controller\ModalResponses;
use App\Entity\Product;
use App\Entity\ProductLink;
use App\Entity\ProductLinkCategory;
use App\Entity\User;
use App\Product\ProductLinks;
use App\Repository\ProductLinkCategoryRepository;
use App\Security\Permission;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * Zakładka „Linki” karty towaru: kategorie w drzewie, dodawanie i edycja linków.
 */
#[Route('/towary')]
#[IsGranted(Permission::PRODUCT_EDIT)]
final class ProductLinkController extends AbstractController
{
    use ModalResponses;

    public function __construct(
        private readonly ProductLinks $links,
        private readonly ProductLinkCategoryRepository $categories,
        private readonly EntityManagerInterface $em,
    ) {
    }

    #[Route('/{id}/linki/nowy', name: 'app_product_link_new', requirements: ['id' => '\d+'], methods: ['GET', 'POST'])]
    public function new(Product $product, Request $request, #[CurrentUser] User $user, ValidatorInterface $validator): Response
    {
        $choices = $this->links->categoryChoices();
        $category = $this->categories->find((int) $request->query->getString('kategoria')) ?? $choices[0] ?? throw $this->createNotFoundException('Najpierw dodaj kategorię linków.');
        $link = (new ProductLink($product, $category))->setAddedBy($user);

        return $this->handleLink($link, $request, $choices, $validator);
    }

    #[Route('/linki/{id}', name: 'app_product_link_edit', requirements: ['id' => '\d+'], methods: ['GET', 'POST'])]
    public function edit(ProductLink $link, Request $request, ValidatorInterface $validator): Response
    {
        return $this->handleLink($link, $request, $this->links->categoryChoices(), $validator);
    }

    #[Route('/linki/{id}/usun', name: 'app_product_link_delete', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function delete(ProductLink $link, Request $request): Response
    {
        $this->assertToken($request);
        $product = $link->getProduct();
        $this->em->remove($link);
        $this->em->flush();

        return $this->visit($request, $this->generateUrl('app_product_links', ['id' => $product->getId()]));
    }

    #[Route('/{id}/linki/kategoria', name: 'app_product_link_category_new', requirements: ['id' => '\d+'], methods: ['GET', 'POST'])]
    public function newCategory(Product $product, Request $request): Response
    {
        $parent = $this->categories->find((int) $request->query->getString('rodzic'));

        return $this->handleCategory($product, null, $parent, $request);
    }

    #[Route('/{id}/linki/kategoria/{category}', name: 'app_product_link_category_edit', requirements: ['id' => '\d+', 'category' => '\d+'], methods: ['GET', 'POST'])]
    public function editCategory(Product $product, ProductLinkCategory $category, Request $request): Response
    {
        return $this->handleCategory($product, $category, $category->getParent(), $request);
    }

    #[Route('/{id}/linki/kategoria/{category}/usun', name: 'app_product_link_category_delete', requirements: ['id' => '\d+', 'category' => '\d+'], methods: ['POST'])]
    public function deleteCategory(Product $product, ProductLinkCategory $category, Request $request): Response
    {
        $this->assertToken($request);
        if ($this->links->deleteCategory($category)) {
            $this->addFlash('success', \sprintf('Usunięto kategorię „%s”.', $category->getName()));
        } else {
            $this->addFlash('error', 'Kategorii z linkami albo podkategoriami nie można usunąć.');
        }

        return $this->visit($request, $this->generateUrl('app_product_links', ['id' => $product->getId()]));
    }

    #[Route('/linki/kategorie/kolejnosc', name: 'app_product_link_category_order', methods: ['POST'])]
    public function reorder(Request $request): Response
    {
        if (!$this->isCsrfTokenValid('product-link', $request->headers->get('X-CSRF-Token', ''))) {
            throw $this->createAccessDeniedException();
        }

        $payload = $request->toArray();
        $parent = isset($payload['parent']) && is_numeric($payload['parent']) ? $this->categories->find((int) $payload['parent']) : null;
        $this->links->reorder($parent, array_values(array_map('intval', array_filter((array) ($payload['ids'] ?? []), 'is_numeric'))));

        return new JsonResponse(['ok' => true]);
    }

    /**
     * @param list<ProductLinkCategory> $choices
     */
    private function handleLink(ProductLink $link, Request $request, array $choices, ValidatorInterface $validator): Response
    {
        $errors = [];
        if ($request->isMethod('POST')) {
            $this->assertToken($request);
            $payload = $request->getPayload();
            $category = $this->categories->find((int) $payload->getString('category'));
            if (null !== $category) {
                $link->setCategory($category);
            }
            $link->setUrl($payload->getString('url'))->setName($payload->getString('name'))->setDescription(trim($payload->getString('description')) ?: null);

            foreach ($validator->validate($link) as $violation) {
                $errors[$violation->getPropertyPath()] = (string) $violation->getMessage();
            }
            if ([] === $errors) {
                $this->em->persist($link);
                $this->em->flush();

                return $this->visit($request, $this->generateUrl('app_product_links', ['id' => $link->getProduct()->getId(), 'kategoria' => $link->getCategory()->getId()]));
            }
        }

        return $this->render('product/link.html.twig', ['link' => $link, 'choices' => $choices, 'errors' => $errors], new Response(null, [] !== $errors ? 422 : 200));
    }

    private function handleCategory(Product $product, ?ProductLinkCategory $category, ?ProductLinkCategory $parent, Request $request): Response
    {
        $error = null;
        if ($request->isMethod('POST')) {
            $this->assertToken($request);
            $name = trim($request->getPayload()->getString('name'));
            if ('' === $name || mb_strlen($name) > 80) {
                $error = 'Podaj nazwę (do 80 znaków).';
            } else {
                if (null === $category) {
                    $category = $this->links->addCategory($name, $parent);
                } else {
                    $category->setName($name);
                    $this->em->flush();
                }

                return $this->visit($request, $this->generateUrl('app_product_links', ['id' => $product->getId(), 'kategoria' => $category->getId(), 'wszystkie' => 1]));
            }
        }

        return $this->render('product/link_category.html.twig', [
            'product' => $product,
            'category' => $category,
            'parent' => $parent,
            'error' => $error,
            'can_delete' => null !== $category && !$this->categories->isUsed($category),
        ], new Response(null, null !== $error ? 422 : 200));
    }

    private function assertToken(Request $request): void
    {
        if (!$this->isCsrfTokenValid('product-link', $request->getPayload()->getString('_token'))) {
            throw $this->createAccessDeniedException();
        }
    }
}
