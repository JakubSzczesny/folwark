<?php

declare(strict_types=1);

namespace App\Export;

use Dompdf\Dompdf;
use Dompdf\Options;
use Symfony\Component\HttpFoundation\HeaderUtils;
use Symfony\Component\HttpFoundation\Response;
use Twig\Environment;

final class PdfRenderer
{
    public function __construct(
        private readonly Environment $twig,
    ) {
    }

    /**
     * @param array<string, mixed> $context
     */
    public function render(string $template, array $context, string $filename, string $orientation = 'portrait'): Response
    {
        $options = new Options();
        $options->setDefaultFont('DejaVu Sans');
        $options->setIsRemoteEnabled(false);
        $options->setChroot([\dirname(__DIR__, 2).'/var']);

        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($this->twig->render($template, $context), 'UTF-8');
        $dompdf->setPaper('A4', $orientation);
        $dompdf->render();

        $response = new Response((string) $dompdf->output());
        $response->headers->set('Content-Type', 'application/pdf');
        $ascii = preg_replace('/[^A-Za-z0-9._-]+/', '_', (string) iconv('UTF-8', 'ASCII//TRANSLIT', $filename)) ?: 'dokument.pdf';
        $response->headers->set('Content-Disposition', HeaderUtils::makeDisposition(HeaderUtils::DISPOSITION_INLINE, $filename, $ascii));

        return $response;
    }
}
