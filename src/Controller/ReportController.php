<?php

declare(strict_types=1);

namespace App\Controller;

use App\Enum\InvoiceKind;
use App\Export\PdfRenderer;
use App\Export\XlsxExporter;
use App\Report\Report;
use App\Report\Reports;
use App\Security\Permission;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Zestawienia do pobrania (specyfikacja 15): XLSX, wybrane także PDF.
 */
#[Route('/zestawienia')]
#[IsGranted(Permission::EXPORT)]
final class ReportController extends AbstractController
{
    public function __construct(
        private readonly Reports $reports,
    ) {
    }

    #[Route('', name: 'app_report_index', methods: ['GET'])]
    public function index(): Response
    {
        $today = new \DateTimeImmutable('today');
        $year = (int) $today->format('Y');

        return $this->render('report/index.html.twig', [
            'month_start' => $today->modify('first day of last month'),
            'month_end' => $today->modify('last day of last month'),
            'year' => $year,
            'diesel_half' => (int) $today->format('n') >= 8 || 1 === (int) $today->format('n') ? 2 : 1,
            'diesel_year' => 1 === (int) $today->format('n') ? $year - 1 : $year,
        ]);
    }

    #[Route('/faktury.{format}', name: 'app_report_invoices', requirements: ['format' => 'xlsx|pdf'], methods: ['GET'])]
    #[IsGranted(Permission::INVOICE_VIEW)]
    public function invoices(Request $request, string $format, PdfRenderer $pdf, XlsxExporter $xlsx): Response
    {
        [$from, $to] = $this->range($request);

        return $this->output($this->reports->invoices($from, $to, InvoiceKind::tryFrom($request->query->getString('rodzaj'))), $format, $pdf, $xlsx);
    }

    #[Route('/olej-napedowy.{format}', name: 'app_report_diesel', requirements: ['format' => 'xlsx|pdf'], methods: ['GET'])]
    #[IsGranted(Permission::INVOICE_VIEW)]
    public function diesel(Request $request, string $format, PdfRenderer $pdf, XlsxExporter $xlsx): Response
    {
        $year = (int) $request->query->getString('rok') ?: (int) date('Y');
        $half = 2 === (int) $request->query->getString('polrocze') ? 2 : 1;
        [$from, $to] = Reports::dieselPeriod(max(2000, min(2100, $year)), $half);

        return $this->output($this->reports->diesel($from, $to), $format, $pdf, $xlsx);
    }

    #[Route('/zakupy.{format}', name: 'app_report_purchases', requirements: ['format' => 'xlsx|pdf'], methods: ['GET'])]
    #[IsGranted(Permission::INVOICE_VIEW)]
    public function purchases(Request $request, string $format, PdfRenderer $pdf, XlsxExporter $xlsx): Response
    {
        [$from, $to] = $this->range($request);

        return $this->output($this->reports->purchases($from, $to), $format, $pdf, $xlsx);
    }

    #[Route('/terminy-maszyn.{format}', name: 'app_report_machines', requirements: ['format' => 'xlsx|pdf'], methods: ['GET'])]
    #[IsGranted(Permission::MACHINE_VIEW)]
    public function machines(string $format, PdfRenderer $pdf, XlsxExporter $xlsx): Response
    {
        return $this->output($this->reports->machineDeadlines(new \DateTimeImmutable('today')), $format, $pdf, $xlsx);
    }

    #[Route('/zamowienia.{format}', name: 'app_report_orders', requirements: ['format' => 'xlsx|pdf'], methods: ['GET'])]
    #[IsGranted(Permission::ORDER_VIEW)]
    public function orders(Request $request, string $format, PdfRenderer $pdf, XlsxExporter $xlsx): Response
    {
        $days = 90 === (int) $request->query->getString('dni') ? 90 : 30;

        return $this->output($this->reports->orders(new \DateTimeImmutable('today'), $days), $format, $pdf, $xlsx);
    }

    /**
     * @return array{\DateTimeImmutable, \DateTimeImmutable}
     */
    private function range(Request $request): array
    {
        $from = self::date($request->query->getString('od')) ?? new \DateTimeImmutable('first day of last month');
        $to = self::date($request->query->getString('do')) ?? $from->modify('last day of this month');

        return $from <= $to ? [$from, $to] : [$to, $from];
    }

    private function output(Report $report, string $format, PdfRenderer $pdf, XlsxExporter $xlsx): Response
    {
        if ('pdf' === $format) {
            return $pdf->render('report/pdf.html.twig', ['report' => $report], $report->filename.'.pdf', \count($report->columns) > 7 ? 'landscape' : 'portrait');
        }

        return $xlsx->response($report->filename.'.xlsx', $report->title, $report->columns, $report->rows, $report->totals);
    }

    private static function date(string $value): ?\DateTimeImmutable
    {
        return 1 === preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) ? new \DateTimeImmutable($value) : null;
    }
}
