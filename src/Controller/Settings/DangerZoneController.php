<?php

declare(strict_types=1);

namespace App\Controller\Settings;

use App\Audit\AuditLogger;
use App\Farm\FarmContext;
use App\Farm\FarmSwitcher;
use App\Farm\FarmWiper;
use App\Security\Permission;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Nieodwracalne operacje na gospodarstwie. Każda wymaga przepisania nazwy gospodarstwa.
 */
#[Route('/ustawienia/strefa-niebezpieczna')]
#[IsGranted(Permission::SETTINGS)]
final class DangerZoneController extends AbstractController
{
    public function __construct(
        private readonly FarmContext $context,
        private readonly FarmWiper $wiper,
    ) {
    }

    #[Route('', name: 'app_settings_danger', methods: ['GET'])]
    public function index(): Response
    {
        return $this->render('settings/danger.html.twig', ['farm' => $this->context->farm()]);
    }

    #[Route('/wyczysc', name: 'app_settings_danger_wipe', methods: ['POST'])]
    public function wipe(Request $request, AuditLogger $audit, EntityManagerInterface $em): Response
    {
        $farm = $this->context->farm();
        if (!$this->confirmed($request, 'danger-wipe')) {
            return $this->redirectToRoute('app_settings_danger', [], Response::HTTP_SEE_OTHER);
        }

        $this->wiper->wipe($farm);
        $audit->log('Farm', $farm->getId(), 'wipe');
        $em->flush();
        $this->addFlash('success', 'Wyczyszczono wszystkie dane gospodarstwa. Słowniki startowe utworzono od nowa.');

        return $this->redirectToRoute('app_dashboard', [], Response::HTTP_SEE_OTHER);
    }

    #[Route('/usun', name: 'app_settings_danger_delete', methods: ['POST'])]
    public function delete(Request $request, FarmSwitcher $switcher): Response
    {
        $farm = $this->context->farm();
        if (!$this->confirmed($request, 'danger-delete')) {
            return $this->redirectToRoute('app_settings_danger', [], Response::HTTP_SEE_OTHER);
        }

        $name = $farm->getName();
        $switcher->leave();
        $this->wiper->delete($farm);
        $this->addFlash('success', \sprintf('Usunięto gospodarstwo „%s” razem ze wszystkimi danymi.', $name));

        return $this->redirectToRoute('app_farm_index', [], Response::HTTP_SEE_OTHER);
    }

    private function confirmed(Request $request, string $tokenId): bool
    {
        if (!$this->isCsrfTokenValid($tokenId, $request->getPayload()->getString('_token'))) {
            throw $this->createAccessDeniedException();
        }

        $typed = trim($request->getPayload()->getString('confirm'));
        if ($typed !== $this->context->farm()->getName()) {
            $this->addFlash('error', 'Wpisana nazwa nie zgadza się z nazwą gospodarstwa – nic nie zostało usunięte.');

            return false;
        }

        return true;
    }
}
