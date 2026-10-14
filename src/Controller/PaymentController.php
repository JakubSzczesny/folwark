<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Invoice;
use App\Payment\TransferData;
use App\Security\Permission;
use App\Settings\SettingKey;
use App\Settings\Settings;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Dane do przelewu za fakturę (okno z kopiowaniem pól) – także źródło dla dodatku BS Pay.
 */
#[IsGranted(Permission::INVOICE_MANAGE)]
final class PaymentController extends AbstractController
{
    #[Route('/faktury/{id}/przelew', name: 'app_invoice_transfer', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function transfer(Invoice $invoice, TransferData $transfers, Settings $settings): Response
    {
        return $this->render('invoice/transfer.html.twig', [
            'invoice' => $invoice,
            'transfer' => $transfers->forInvoice($invoice),
            'bspay_enabled' => $settings->bool(SettingKey::BSPAY_ENABLED),
            'bank_url' => $settings->string(SettingKey::BSPAY_BANK_URL),
        ]);
    }
}
