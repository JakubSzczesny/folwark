<?php

declare(strict_types=1);

namespace App\Controller;

use App\Search\GlobalSearch;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class SearchController extends AbstractController
{
    #[Route('/szukaj', name: 'app_search', methods: ['GET'])]
    public function search(Request $request, GlobalSearch $search): Response
    {
        $query = trim($request->query->getString('q'));
        $template = $request->headers->has('X-Requested-With') ? 'search/_results.html.twig' : 'search/index.html.twig';

        return $this->render($template, [
            'query' => $query,
            'groups' => $search->search($query, 'search/index.html.twig' === $template ? 20 : 5),
            'min_length' => GlobalSearch::MIN_LENGTH,
        ]);
    }
}
