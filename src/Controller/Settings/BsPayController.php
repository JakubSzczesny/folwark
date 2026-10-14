<?php

declare(strict_types=1);

namespace App\Controller\Settings;

use App\Form\Settings\BsPaySettingsType;
use App\Security\Permission;
use App\Settings\SettingsForm;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * BS Pay – przygotowanie przelewów za faktury do wklejenia w bankowości BS Bydgoszcz.
 */
#[Route('/ustawienia/bs-pay')]
#[IsGranted(Permission::SETTINGS)]
final class BsPayController extends AbstractController
{
    #[Route('', name: 'app_settings_bspay', methods: ['GET', 'POST'])]
    public function index(Request $request, SettingsForm $settingsForm, EntityManagerInterface $em): Response
    {
        $form = $this->createForm(BsPaySettingsType::class, $settingsForm->data(BsPaySettingsType::KEYS));
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $settingsForm->save($form, BsPaySettingsType::KEYS);
            $em->flush();
            $this->addFlash('success', 'Zapisano ustawienia BS Pay.');

            return $this->redirectToRoute('app_settings_bspay', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('settings/bspay.html.twig', ['form' => $form], new Response(null, $form->isSubmitted() ? 422 : 200));
    }
}
