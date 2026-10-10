<?php

declare(strict_types=1);

namespace App\Controller\Settings;

use App\Crypto\SecretBox;
use App\Entity\User;
use App\Form\Settings\KsefSettingsType;
use App\Ksef\KsefClient;
use App\Ksef\KsefException;
use App\Message\SyncKsefMetadata;
use App\Repository\KsefSyncRepository;
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

#[Route('/ustawienia/ksef')]
final class KsefSettingsController extends AbstractController
{
    public function __construct(
        private readonly Settings $settings,
        private readonly SettingsForm $settingsForm,
        private readonly EntityManagerInterface $em,
    ) {
    }

    #[Route('', name: 'app_settings_ksef', methods: ['GET', 'POST'])]
    public function index(Request $request, SecretBox $secrets, KsefSyncRepository $syncs): Response
    {
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

            $this->em->flush();
            $this->addFlash('success', 'Zapisano ustawienia KSeF.');

            return $this->redirectToRoute('app_settings_ksef', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('settings/ksef.html.twig', [
            'form' => $form,
            'syncs' => $syncs->findRecent(15),
            'cursors' => (array) $this->settings->get(SettingKey::KSEF_CURSORS),
            'blocked_until' => $this->settings->date(SettingKey::KSEF_BLOCKED_UNTIL),
        ]);
    }

    #[Route('/test', name: 'app_settings_ksef_test', methods: ['POST'])]
    public function test(Request $request, KsefClient $client): Response
    {
        $this->assertToken($request);

        try {
            $this->addFlash('success', $client->testConnection());
        } catch (KsefException|\RuntimeException $e) {
            $this->addFlash('error', 'Test połączenia nieudany: '.$e->getMessage());
        }
        $this->em->flush();

        return $this->redirectToRoute('app_settings_ksef', [], Response::HTTP_SEE_OTHER);
    }

    #[Route('/synchronizuj', name: 'app_settings_ksef_sync', methods: ['POST'])]
    public function sync(Request $request, MessageBusInterface $bus, #[CurrentUser] User $user): Response
    {
        $this->assertToken($request);
        $bus->dispatch(new SyncKsefMetadata(true, $user->getId()));
        $this->addFlash('success', 'Synchronizacja została zlecona.');

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
