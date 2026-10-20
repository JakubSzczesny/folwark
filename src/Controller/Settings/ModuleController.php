<?php

declare(strict_types=1);

namespace App\Controller\Settings;

use App\Module\Modules;
use App\Security\Permission;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Włączanie i wyłączanie modułów z menu. Wyłączenie niczego nie usuwa.
 */
#[Route('/ustawienia/moduly')]
#[IsGranted(Permission::SETTINGS)]
final class ModuleController extends AbstractController
{
    public function __construct(
        private readonly Modules $modules,
    ) {
    }

    #[Route('', name: 'app_settings_modules', methods: ['GET', 'POST'])]
    public function index(Request $request): Response
    {
        if ($request->isMethod('POST')) {
            $this->assertToken($request);
            $enabled = $request->getPayload()->all('enabled');
            $this->modules->setDisabled(array_values(array_filter(
                array_keys(Modules::DEFINITIONS),
                static fn (string $key): bool => !\in_array($key, $enabled, true),
            )));
            $this->addFlash('success', 'Zapisano moduły.');

            return $this->redirectToRoute('app_settings_modules', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('settings/modules.html.twig', ['modules' => $this->modules->overview()]);
    }

    #[Route('/{key}/{state}', name: 'app_settings_module_toggle', requirements: ['state' => 'wlacz|wylacz'], methods: ['POST'])]
    public function toggle(string $key, string $state, Request $request): Response
    {
        $this->assertToken($request);
        if (!Modules::exists($key)) {
            throw $this->createNotFoundException();
        }

        if ('wlacz' === $state) {
            $this->modules->enable($key);
            $this->addFlash('success', \sprintf('Włączono moduł „%s”.', Modules::label($key)));
        } else {
            $this->modules->disable($key);
            $this->addFlash('success', \sprintf('Wyłączono moduł „%s”. Dane zostają – można go z powrotem włączyć w Ustawienia → Moduły.', Modules::label($key)));
        }

        return $this->redirectToRoute('wlacz' === $state ? 'app_settings_modules' : 'app_dashboard', [], Response::HTTP_SEE_OTHER);
    }

    private function assertToken(Request $request): void
    {
        if (!$this->isCsrfTokenValid('modules', $request->getPayload()->getString('_token'))) {
            throw $this->createAccessDeniedException();
        }
    }
}
