<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Invoice;
use App\Entity\InvoiceItem;
use App\Entity\User;
use App\Farm\FarmContext;
use App\Product\AssignmentRow;
use App\Product\ItemAssigner;
use App\Product\UnitParser;
use App\Repository\CostCenterRepository;
use App\Repository\InvoiceItemRepository;
use App\Repository\ProductRepository;
use App\Repository\WarehouseRepository;
use App\Security\Permission;
use App\Settings\SettingKey;
use App\Settings\Settings;
use App\Warehouse\InvoiceReceipt;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Przypisanie pozycji faktury do towarów z kartoteki (specyfikacja 7.8).
 */
#[Route('/faktury')]
#[IsGranted(Permission::INVOICE_MANAGE)]
final class InvoiceAssignController extends AbstractController
{
    public function __construct(
        private readonly ItemAssigner $assigner,
    ) {
    }

    #[Route('/{id}/towary', name: 'app_invoice_assign', requirements: ['id' => '\d+'], methods: ['GET', 'POST'])]
    public function assign(Invoice $invoice, Request $request, #[CurrentUser] User $user, ProductRepository $products, CostCenterRepository $centers, WarehouseRepository $warehouses, InvoiceReceipt $receipt, Settings $settings): Response
    {
        $rows = $this->assigner->rows($invoice);
        $existingPz = $receipt->existing($invoice);
        $errors = [];

        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('invoice-assign', $request->getPayload()->getString('_token'))) {
                throw $this->createAccessDeniedException();
            }

            $data = $request->getPayload()->all('rows');
            foreach ($rows as $row) {
                $id = (int) $row->item->getId();
                $input = \is_array($data[$id] ?? null) ? $data[$id] : [];
                $row->skip = !empty($input['skip']);
                $row->product = $row->skip ? null : $products->find((int) ($input['product'] ?? 0));
                $row->factor = self::number($input['factor'] ?? null);
                $row->costCenter = $centers->find((int) ($input['costCenter'] ?? 0));

                if (!$row->skip && null === $row->product) {
                    $errors[$id] = 'Wybierz towar albo zaznacz „pomiń”.';
                } elseif (!$row->skip && (null === $row->factor || $row->factor <= 0) && null !== $row->item->getQuantity()) {
                    $errors[$id] = 'Podaj, ile jednostek towaru to jedna jednostka z faktury.';
                }
            }

            if ([] === $errors) {
                $this->assigner->save($invoice, $rows, $user);
                $this->addFlash('success', 'Przypisano towary. Zapamiętaliśmy to – kolejne faktury od tego dostawcy wypełnią się same.');
                $warehouse = $warehouses->find((int) $request->getPayload()->getString('pz_warehouse'));
                if (null === $existingPz && null !== $warehouse && $request->getPayload()->getBoolean('pz') && $this->isGranted(Permission::DOCUMENT_EDIT)) {
                    [$document, $warnings] = $receipt->create($invoice, $warehouse, $user);
                    if (null !== $document) {
                        $this->addFlash('success', \sprintf('Utworzono przyjęcie %s.', $document->getNumber()));
                    }
                    foreach ($warnings as $warning) {
                        $this->addFlash('warning', $warning);
                    }
                }

                return $this->redirectToRoute('app_invoice_show', ['id' => $invoice->getId()], Response::HTTP_SEE_OTHER);
            }
        }

        return $this->render('invoice/assign.html.twig', [
            'invoice' => $invoice,
            'rows' => $rows,
            'errors' => $errors,
            'centers' => $centers->findActive(),
            'existing_pz' => $existingPz,
            'warehouses' => $warehouses->findBy(['active' => true], ['name' => 'ASC']),
            'create_pz' => $settings->bool(SettingKey::WAREHOUSE_CREATE_PZ),
            'sources' => [AssignmentRow::REMEMBERED => 'zapamiętane', AssignmentRow::PROPOSAL => 'propozycja', AssignmentRow::ASSIGNED => 'przypisane'],
        ], new Response(null, [] !== $errors ? 422 : 200));
    }

    /**
     * Przelicznik po wyborze towaru w wierszu: ile jednostek towaru to jedna jednostka z faktury.
     */
    #[Route('/towary/przelicznik', name: 'app_invoice_assign_factor', methods: ['GET'])]
    public function factor(Request $request, InvoiceItemRepository $items, ProductRepository $products, UnitParser $units, FarmContext $context): JsonResponse
    {
        $item = $items->find((int) $request->query->getString('pozycja'));
        $product = $products->find((int) $request->query->getString('towar'));
        if (!$item instanceof InvoiceItem || null === $product || $item->getInvoice()->getFarm()?->getId() !== $context->farmId()) {
            throw $this->createNotFoundException();
        }

        return new JsonResponse([
            'factor' => $units->factor($item->getName(), $item->getUnit(), $product),
            'unit' => $product->getUnitSymbol(),
        ]);
    }

    private static function number(mixed $value): ?float
    {
        $value = str_replace([' ', "\u{00A0}", ','], ['', '', '.'], trim((string) $value));

        return is_numeric($value) ? (float) $value : null;
    }
}
