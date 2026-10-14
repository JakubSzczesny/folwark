<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Invoice;
use App\Payment\TransferData;
use App\Security\Permission;
use App\Settings\SettingKey;
use App\Settings\Settings;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Dane do przelewu za fakturę (okno z kopiowaniem pól) – także źródło dla dodatku BS Pay.
 */
#[IsGranted(Permission::INVOICE_MANAGE)]
final class PaymentController extends AbstractController
{
    public const string DEFAULT_BANK_URL = 'https://i24.bsbydgoszcz.pl/';

    /** Pola formularza „Przelew krajowy” w bankowości BS Bydgoszcz (i-Bank, PrimeFaces): klucz => id pola bez prefiksu. */
    private const array BANK_FIELDS = [
        'account' => 'numerRachunkuField',
        'name1' => 'nazwaAdresataPelnaField1',
        'name2' => 'nazwaAdresataPelnaField2',
        'street' => 'adresAdresataField',
        'postalCode' => 'kodPocztowyAdresataField',
        'city' => 'miejscowoscAdresataField',
        'amount' => 'kwotaField',
        'title1' => 'tytulField1',
        'title2' => 'tytulField2',
        'title3' => 'tytulField3',
        'title4' => 'tytulField4',
    ];

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

    /**
     * Skrypt Tampermonkey „Folwark – BS Pay”: na stronie Folwarku przejmuje dane przelewu,
     * w bankowości BS wypełnia formularz przelewu krajowego. Adresy wstawiamy z tej instalacji i ustawień.
     */
    #[Route('/bs-pay/folwark-bs-pay.user.js', name: 'app_bspay_userscript', methods: ['GET'])]
    public function userscript(Request $request, Settings $settings, #[Autowire('%kernel.project_dir%')] string $projectDir): Response
    {
        $bankUrl = $settings->string(SettingKey::BSPAY_BANK_URL) ?: self::DEFAULT_BANK_URL;
        $bankOrigin = (string) preg_replace('~^(https://[^/]+).*$~', '$1', $bankUrl);
        $folwarkOrigin = $request->getSchemeAndHttpHost();

        $response = $this->render('bspay/userscript.js.twig', [
            'version' => '1.0.'.(int) filemtime($projectDir.'/templates/bspay/userscript.js.twig'),
            'folwark_origin' => $folwarkOrigin,
            'bank_origin' => $bankOrigin,
            'script_url' => $this->generateUrl('app_bspay_userscript', [], UrlGeneratorInterface::ABSOLUTE_URL),
            'config' => [
                'folwarkOrigin' => $folwarkOrigin,
                'bankOrigin' => $bankOrigin,
                'bankUrl' => $bankUrl,
                'transferPath' => 'dodawanie-przelewow-elixir',
                'fieldPrefix' => 'ibankForm:elixirPanel:',
                'fields' => self::BANK_FIELDS,
                'required' => ['account', 'name1', 'amount', 'title1'],
            ],
        ]);
        $response->headers->set('Content-Type', 'text/javascript; charset=UTF-8');
        $response->headers->set('Cache-Control', 'no-cache');

        return $response;
    }
}
