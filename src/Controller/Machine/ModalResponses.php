<?php

declare(strict_types=1);

namespace App\Controller\Machine;

use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\UX\Turbo\TurboBundle;

/**
 * Po zapisie formularza w oknie dialogowym przechodzimy na wskazaną stronę (strumień Turbo „visit”);
 * bez Turbo zwykłe przekierowanie.
 */
trait ModalResponses
{
    private function visit(Request $request, string $url): Response
    {
        if ('modal' === $request->headers->get('Turbo-Frame') && TurboBundle::STREAM_FORMAT === $request->getPreferredFormat()) {
            $request->setRequestFormat(TurboBundle::STREAM_FORMAT);

            return $this->render('_partials/visit.stream.html.twig', ['url' => $url]);
        }

        return new RedirectResponse($url, Response::HTTP_SEE_OTHER);
    }
}
