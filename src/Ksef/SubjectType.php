<?php

declare(strict_types=1);

namespace App\Ksef;

use App\Enum\InvoiceKind;

/**
 * Rola gospodarstwa na fakturze (InvoiceQuerySubjectType w API).
 * Także w FA_RR Podmiot1 to dostawca (rolnik), a Podmiot2 nabywca, więc podział jest wspólny.
 */
enum SubjectType: string
{
    case Seller = 'Subject1';
    case Buyer = 'Subject2';

    public function invoiceKind(): InvoiceKind
    {
        return self::Seller === $this ? InvoiceKind::Income : InvoiceKind::Cost;
    }
}
