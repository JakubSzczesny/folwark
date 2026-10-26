<?php

declare(strict_types=1);

namespace App\Controller;

use App\Farm\FarmContext;
use App\Gmail\GmailMailbox;
use App\Repository\ContractorRepository;
use App\Search\GlobalSearch;
use App\Search\SearchResult;
use App\Security\Permission;
use App\Twig\FormatExtension;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;

final class SearchController extends AbstractController
{
    /** W poczcie szukamy od 3 znaków – krótsze zapytania dają z Gmaila tylko szum. */
    public const int MAIL_MIN_LENGTH = 3;

    #[Route('/szukaj', name: 'app_search', methods: ['GET'])]
    public function search(Request $request, GlobalSearch $search, GmailMailbox $mailbox): Response
    {
        $query = trim($request->query->getString('q'));
        $template = $request->headers->has('X-Requested-With') ? 'search/_results.html.twig' : 'search/index.html.twig';
        $limit = 'search/index.html.twig' === $template ? 20 : 5;

        return $this->render($template, [
            'query' => $query,
            'groups' => $search->search($query, $limit),
            'min_length' => GlobalSearch::MIN_LENGTH,
            'limit' => $limit,
            // Poczta doczytuje się osobno (ramka), żeby czekanie na Gmaila nie wstrzymywało pozostałych wyników.
            'mail_search' => mb_strlen($query) >= self::MAIL_MIN_LENGTH && $this->isGranted(Permission::CONTRACTOR_VIEW) && $mailbox->isConfigured(),
        ]);
    }

    /**
     * Grupa „Poczta”: maile kontrahentów (z adresem e-mail na karcie) pasujące do zapytania.
     */
    #[Route('/szukaj/poczta', name: 'app_search_mail', methods: ['GET'])]
    #[IsGranted(Permission::CONTRACTOR_VIEW)]
    public function mail(Request $request, GmailMailbox $mailbox, ContractorRepository $contractors, CacheInterface $cache, FarmContext $context): Response
    {
        $query = trim($request->query->getString('q'));
        $limit = min(20, max(1, $request->query->getInt('limit', 5)));
        $results = [];
        $error = null;

        if (mb_strlen($query) >= self::MAIL_MIN_LENGTH && $mailbox->isConfigured()) {
            try {
                // Krótka pamięć podręczna – pisanie i kasowanie liter nie pyta Gmaila za każdym razem.
                $key = \sprintf('search_mail_%d_%s', $context->farmId(), hash('xxh128', $limit.'|'.mb_strtolower($query)));
                $results = $cache->get($key, function (ItemInterface $item) use ($query, $limit, $mailbox, $contractors): array {
                    $item->expiresAfter(120);

                    return $this->mailResults($query, $limit, $mailbox, $contractors);
                });
            } catch (\Throwable $e) {
                $error = 'Nie udało się przeszukać Gmaila: '.$e->getMessage();
            }
        }

        return $this->render('search/_mail_results.html.twig', ['results' => $results, 'error' => $error]);
    }

    /**
     * @return list<SearchResult>
     */
    private function mailResults(string $query, int $limit, GmailMailbox $mailbox, ContractorRepository $contractors): array
    {
        $byAddress = [];
        foreach ($contractors->findWithEmail() as $contractor) {
            foreach ($contractor->getEmailAddresses() as $address) {
                $byAddress[$address] ??= $contractor;
            }
        }

        $results = [];
        foreach ($mailbox->search($query, array_map('strval', array_keys($byAddress)), $limit) as $mail) {
            $contractor = null;
            foreach ($mail->participants as $address) {
                if (isset($byAddress[$address])) {
                    $contractor = $byAddress[$address];
                    break;
                }
            }
            if (null === $contractor) {
                continue;
            }
            $results[] = new SearchResult(
                $mail->subject,
                $this->generateUrl('app_contractor_mail', ['id' => $contractor->getId(), 'uid' => $mail->uid]),
                $contractor->getDisplayName().($mail->date ? ' · '.FormatExtension::date($mail->date) : ''),
            );
        }

        return $results;
    }
}
