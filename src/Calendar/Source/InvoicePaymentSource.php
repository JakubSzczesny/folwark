<?php

declare(strict_types=1);

namespace App\Calendar\Source;

use App\Calendar\CalendarEntry;
use App\Calendar\CalendarSource;
use App\Entity\Invoice;
use App\Enum\InvoiceKind;
use App\Enum\InvoiceStatus;
use App\Enum\PaymentStatus;
use App\Repository\InvoiceRepository;
use App\Security\Permission;
use App\Twig\FormatExtension;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;

/**
 * Terminy płatności nieopłaconych faktur zakupowych.
 */
final class InvoicePaymentSource implements CalendarSource
{
    public function __construct(
        private readonly InvoiceRepository $invoices,
        private readonly UrlGeneratorInterface $urls,
        private readonly AuthorizationCheckerInterface $auth,
    ) {
    }

    public function label(): string
    {
        return 'Płatności faktur';
    }

    public function permission(): string
    {
        return Permission::INVOICE_VIEW;
    }

    public function entries(\DateTimeImmutable $from, \DateTimeImmutable $to): array
    {
        /** @var list<Invoice> $invoices */
        $invoices = $this->invoices->createQueryBuilder('i')
            ->where('i.kind = :cost')
            ->andWhere('i.payment = :unpaid')
            ->andWhere('i.status != :skipped')
            ->andWhere('i.paymentDue BETWEEN :from AND :to')
            ->setParameter('cost', InvoiceKind::Cost)
            ->setParameter('unpaid', PaymentStatus::Unpaid)
            ->setParameter('skipped', InvoiceStatus::Skipped)
            ->setParameter('from', $from->format('Y-m-d'))
            ->setParameter('to', $to->format('Y-m-d'))
            ->getQuery()
            ->getResult();

        $amounts = $this->auth->isGranted(Permission::AMOUNTS_VIEW);
        $entries = [];
        foreach ($invoices as $invoice) {
            $due = $invoice->getPaymentDue();
            \assert(null !== $due);
            $entries[] = CalendarEntry::deadline(
                'Płatność: '.$invoice->getContractorLabel(),
                $due,
                $this->label(),
                $this->urls->generate('app_invoice_show', ['id' => $invoice->getId()]),
                $invoice->getNumber().($amounts ? ' · '.FormatExtension::money($invoice->getGross(), $invoice->getCurrency()) : ''),
            );
        }

        return $entries;
    }
}
