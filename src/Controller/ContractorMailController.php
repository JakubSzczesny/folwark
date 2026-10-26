<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Contractor;
use App\Gmail\GmailException;
use App\Gmail\GmailMailbox;
use App\Gmail\MailMessage;
use App\Security\Permission;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\HeaderUtils;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\String\Slugger\AsciiSlugger;

/**
 * Podgląd maili kontrahenta ze skrzynki Gmail gospodarstwa (lista jest w zakładce
 * „Korespondencja” na karcie kontrahenta).
 */
#[Route('/kontrahenci/{id}/poczta', requirements: ['id' => '\d+'])]
#[IsGranted(Permission::CONTRACTOR_VIEW)]
final class ContractorMailController extends AbstractController
{
    public function __construct(
        private readonly GmailMailbox $mailbox,
    ) {
    }

    #[Route('/{uid}', name: 'app_contractor_mail', requirements: ['uid' => '\d+'], methods: ['GET'])]
    public function show(Contractor $contractor, int $uid, Request $request): Response
    {
        $remoteImages = $request->query->getBoolean('obrazy');
        $error = null;
        $message = null;
        try {
            $message = $this->load($contractor, $uid);
        } catch (GmailException $e) {
            $error = $e->getMessage();
        }

        return $this->render('contractor/mail.html.twig', [
            'contractor' => $contractor,
            'message' => $message,
            'error' => $error,
            'remote_images' => $remoteImages,
            'has_remote_images' => null !== $message?->html && 1 === preg_match('/<img[^>]+src=["\']?https?:/i', $message->html),
            'mailbox_address' => $this->mailbox->address(),
        ]);
    }

    #[Route('/{uid}/zalacznik/{index}', name: 'app_contractor_mail_attachment', requirements: ['uid' => '\d+', 'index' => '\d+'], methods: ['GET'])]
    public function attachment(Contractor $contractor, int $uid, int $index, Request $request): Response
    {
        try {
            $attachment = $this->load($contractor, $uid)->attachment($index) ?? throw $this->createNotFoundException('Nie ma takiego załącznika.');
        } catch (GmailException $e) {
            throw new NotFoundHttpException($e->getMessage(), $e);
        }

        $inline = $attachment->isPreviewable() && !$request->query->getBoolean('pobierz');
        $fallback = (new AsciiSlugger())->slug(pathinfo($attachment->name, \PATHINFO_FILENAME))->toString() ?: 'zalacznik';
        $extension = pathinfo($attachment->name, \PATHINFO_EXTENSION);
        $fallback .= '' !== $extension ? '.'.preg_replace('/[^A-Za-z0-9]/', '', $extension) : '';

        $response = new Response($attachment->content);
        $response->headers->set('Content-Type', $inline ? $attachment->mimeType.('text/plain' === $attachment->mimeType ? '; charset=utf-8' : '') : 'application/octet-stream');
        $response->headers->set('Content-Disposition', HeaderUtils::makeDisposition($inline ? HeaderUtils::DISPOSITION_INLINE : HeaderUtils::DISPOSITION_ATTACHMENT, $attachment->name, $fallback));
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        // Przeglądarka Chrome nie pokazuje PDF-a w trybie sandbox, a obrazy i tekst z nosniff i tak nie wykonają kodu.
        if ('application/pdf' !== $attachment->mimeType) {
            $response->headers->set('Content-Security-Policy', 'sandbox');
        }
        $response->setPrivate();

        return $response;
    }

    private function load(Contractor $contractor, int $uid): MailMessage
    {
        if (!$this->mailbox->isConfigured()) {
            throw $this->createNotFoundException('Skrzynka Gmail nie jest podłączona.');
        }

        // Wiadomość, która nie dotyczy adresów kontrahenta, traktujemy jak nieistniejącą –
        // przez adres karty kontrahenta nie da się przeczytać reszty skrzynki.
        return $this->mailbox->message($uid, $contractor->getEmailAddresses())
            ?? throw $this->createNotFoundException('Nie znaleziono wiadomości.');
    }
}
