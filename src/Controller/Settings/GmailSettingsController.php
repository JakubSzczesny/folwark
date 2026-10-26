<?php

declare(strict_types=1);

namespace App\Controller\Settings;

use App\Crypto\SecretBox;
use App\Farm\FarmContext;
use App\Form\Settings\GmailSettingsType;
use App\Gmail\GmailException;
use App\Gmail\GmailMailbox;
use App\Gmail\MailIndexer;
use App\Message\SyncGmailIndex;
use App\Security\Permission;
use App\Settings\SettingKey;
use App\Settings\Settings;
use App\Settings\SettingsForm;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Skrzynka Gmail gospodarstwa – korespondencja na karcie kontrahenta.
 */
#[Route('/ustawienia/gmail')]
#[IsGranted(Permission::SETTINGS)]
final class GmailSettingsController extends AbstractController
{
    public function __construct(
        private readonly Settings $settings,
        private readonly EntityManagerInterface $em,
    ) {
    }

    #[Route('', name: 'app_settings_gmail', methods: ['GET', 'POST'])]
    public function index(Request $request, SettingsForm $settingsForm, SecretBox $secrets, GmailMailbox $mailbox, MessageBusInterface $bus, FarmContext $context): Response
    {
        $form = $this->createForm(GmailSettingsType::class, $settingsForm->data(GmailSettingsType::KEYS), [
            'password_set' => null !== $this->settings->string(SettingKey::GMAIL_APP_PASSWORD),
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $settingsForm->save($form, GmailSettingsType::KEYS);
            $address = $this->settings->string(SettingKey::GMAIL_ADDRESS);
            $this->settings->set(SettingKey::GMAIL_ADDRESS, null === $address ? null : mb_strtolower(trim($address)), false);

            $password = preg_replace('/\s+/', '', (string) $form->get('app_password')->getData());
            if ('' !== $password) {
                $this->settings->set(SettingKey::GMAIL_APP_PASSWORD, $secrets->encrypt((string) $password), false);
            }
            $this->em->flush();
            $this->addFlash('success', 'Zapisano ustawienia Gmaila.');

            if ($mailbox->isConfigured() && $this->testConnection($mailbox)) {
                $bus->dispatch(new SyncGmailIndex($context->farmId()));
            }

            return $this->redirectToRoute('app_settings_gmail', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('settings/gmail.html.twig', [
            'form' => $form,
            'configured' => $mailbox->isConfigured(),
        ], new Response(null, $form->isSubmitted() ? 422 : 200));
    }

    #[Route('/test', name: 'app_settings_gmail_test', methods: ['POST'])]
    public function test(Request $request, GmailMailbox $mailbox): Response
    {
        $this->assertToken($request);
        $this->testConnection($mailbox);

        return $this->redirectToRoute('app_settings_gmail', [], Response::HTTP_SEE_OTHER);
    }

    #[Route('/odlacz', name: 'app_settings_gmail_disconnect', methods: ['POST'])]
    public function disconnect(Request $request, MailIndexer $indexer): Response
    {
        $this->assertToken($request);
        $indexer->clear();
        $this->settings->set(SettingKey::GMAIL_APP_PASSWORD, null, false);
        $this->settings->set(SettingKey::GMAIL_ADDRESS, null);
        $this->addFlash('success', 'Odłączono skrzynkę Gmail. Hasło do aplikacji możesz też unieważnić w koncie Google.');

        return $this->redirectToRoute('app_settings_gmail', [], Response::HTTP_SEE_OTHER);
    }

    private function testConnection(GmailMailbox $mailbox): bool
    {
        try {
            $this->addFlash('success', $mailbox->test().' Maile kontrahentów zsynchronizują się w tle w ciągu minuty.');

            return true;
        } catch (GmailException $e) {
            $this->addFlash('error', $e->getMessage());
        } catch (\Throwable $e) {
            $this->addFlash('error', 'Test połączenia nieudany: '.$e->getMessage());
        } finally {
            $mailbox->reset();
        }

        return false;
    }

    private function assertToken(Request $request): void
    {
        if (!$this->isCsrfTokenValid('gmail-settings', $request->getPayload()->getString('_token'))) {
            throw $this->createAccessDeniedException();
        }
    }
}
