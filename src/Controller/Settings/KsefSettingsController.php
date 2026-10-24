<?php

declare(strict_types=1);

namespace App\Controller\Settings;

use App\Crypto\SecretBox;
use App\Entity\User;
use App\Farm\FarmContext;
use App\Form\Settings\KsefSettingsType;
use App\Ksef\KsefClient;
use App\Ksef\KsefClientMode;
use App\Ksef\KsefClientSelector;
use App\Ksef\KsefException;
use App\Ksef\KsefIdentity;
use App\Message\SyncKsefMetadata;
use App\Repository\KsefSyncRepository;
use App\Security\Permission;
use App\Settings\SettingKey;
use App\Settings\Settings;
use App\Settings\SettingsForm;
use App\Util\Nip;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/ustawienia/ksef')]
#[IsGranted(Permission::SETTINGS)]
final class KsefSettingsController extends AbstractController
{
    public function __construct(
        private readonly Settings $settings,
        private readonly SettingsForm $settingsForm,
        private readonly EntityManagerInterface $em,
    ) {
    }

    #[Route('', name: 'app_settings_ksef', methods: ['GET', 'POST'])]
    public function index(Request $request, SecretBox $secrets, KsefSyncRepository $syncs, KsefIdentity $identity, KsefClientSelector $clients): Response
    {
        $before = [$this->settings->string(SettingKey::KSEF_ENVIRONMENT), $identity->nip()];

        $form = $this->createForm(KsefSettingsType::class, $this->settingsForm->data(KsefSettingsType::KEYS), [
            'token_set' => null !== $this->settings->string(SettingKey::KSEF_TOKEN),
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->settingsForm->save($form, KsefSettingsType::KEYS);
            $this->settings->set(SettingKey::KSEF_NIP, Nip::normalize($this->settings->string(SettingKey::KSEF_NIP)), false);

            $token = trim((string) $form->get('token')->getData());
            if ('' !== $token) {
                $this->settings->set(SettingKey::KSEF_TOKEN, $secrets->encrypt($token), false);
            }

            $this->addFlash('success', 'Zapisano ustawienia KSeF.');
            if ($before !== [$this->settings->string(SettingKey::KSEF_ENVIRONMENT), $identity->nip()]) {
                $this->settings->set(SettingKey::KSEF_CURSORS, [], false);
                $this->settings->set(SettingKey::KSEF_BLOCKED_UNTIL, null, false);
                $this->addFlash('warning', 'Zmieniono środowisko lub NIP – następna synchronizacja pobierze faktury od daty początkowej. Faktury pobrane wcześniej zostały w bazie; jeśli to dane testowe, wyczyść je w Strefie niebezpiecznej.');
            }
            $this->em->flush();

            return $this->redirectToRoute('app_settings_ksef', [], Response::HTTP_SEE_OTHER);
        }

        $cursors = (array) $this->settings->get(SettingKey::KSEF_CURSORS);

        return $this->render('settings/ksef.html.twig', [
            'form' => $form,
            'syncs' => $syncs->findRecent(15),
            'cursors' => $cursors,
            'cursors_stale' => [] !== $cursors && ($cursors['source'] ?? null) !== $clients->source(),
            'blocked_until' => $this->settings->date(SettingKey::KSEF_BLOCKED_UNTIL),
            'simulator' => KsefClientMode::Fake === $clients->mode(),
            'client_problem' => $clients->configurationError(),
        ]);
    }

    #[Route('/test', name: 'app_settings_ksef_test', methods: ['POST'])]
    public function test(Request $request, KsefClient $client, KsefClientSelector $clients): Response
    {
        $this->assertToken($request);

        $problem = $clients->configurationError();
        if (null !== $problem) {
            $this->addFlash('error', 'Test połączenia nieudany: '.$problem);

            return $this->redirectToRoute('app_settings_ksef', [], Response::HTTP_SEE_OTHER);
        }

        try {
            $this->addFlash('success', $client->testConnection());
        } catch (KsefException|\RuntimeException $e) {
            $this->addFlash('error', 'Test połączenia nieudany: '.$e->getMessage());
        }
        $this->em->flush();

        return $this->redirectToRoute('app_settings_ksef', [], Response::HTTP_SEE_OTHER);
    }

    #[Route('/synchronizuj', name: 'app_settings_ksef_sync', methods: ['POST'])]
    public function sync(Request $request, MessageBusInterface $bus, #[CurrentUser] User $user, FarmContext $context): Response
    {
        $this->assertToken($request);
        $bus->dispatch(new SyncKsefMetadata(true, $user->getId(), $context->farmId()));
        $this->addFlash('success', 'Synchronizacja została zlecona – wykona się w tle za kilka sekund. Odśwież stronę, żeby zobaczyć wynik.');

        return $this->redirectToRoute('app_settings_ksef', [], Response::HTTP_SEE_OTHER);
    }

    #[Route('/od-nowa', name: 'app_settings_ksef_reset', methods: ['POST'])]
    public function reset(Request $request): Response
    {
        $this->assertToken($request);
        $this->settings->set(SettingKey::KSEF_CURSORS, [], false);
        $this->settings->set(SettingKey::KSEF_BLOCKED_UNTIL, null);
        $this->addFlash('success', 'Następna synchronizacja pobierze metadane od daty początkowej. Istniejące faktury nie zostaną zdublowane.');

        return $this->redirectToRoute('app_settings_ksef', [], Response::HTTP_SEE_OTHER);
    }

    private function assertToken(Request $request): void
    {
        if (!$this->isCsrfTokenValid('ksef-settings', $request->getPayload()->getString('_token'))) {
            throw $this->createAccessDeniedException();
        }
    }
}
