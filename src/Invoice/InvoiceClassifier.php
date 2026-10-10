<?php

declare(strict_types=1);

namespace App\Invoice;

use App\Enum\InvoiceKind;
use App\Ksef\Dto\InvoiceMetadata;
use App\Ksef\SubjectType;

/**
 * Kosztowa: gospodarstwo jest nabywcą. Przychodowa: gospodarstwo jest sprzedawcą/dostawcą
 * (w FA_RR dostawcą-rolnikiem jest Podmiot1, więc reguła jest ta sama).
 */
final class InvoiceClassifier
{
    public function classify(InvoiceMetadata $metadata, ?string $farmNip, SubjectType $queriedAs): InvoiceKind
    {
        if (null !== $farmNip) {
            if ($metadata->sellerNip === $farmNip) {
                return InvoiceKind::Income;
            }
            if ('Nip' === $metadata->buyerIdentifierType && $metadata->buyerIdentifier === $farmNip) {
                return InvoiceKind::Cost;
            }
        }

        return $queriedAs->invoiceKind();
    }

    /**
     * Druga strona faktury: dla kosztowej sprzedawca, dla przychodowej nabywca.
     *
     * @return array{nip: ?string, name: ?string, identifier: ?string}
     */
    public function counterparty(InvoiceMetadata $metadata, InvoiceKind $kind): array
    {
        if (InvoiceKind::Cost === $kind) {
            return ['nip' => $metadata->sellerNip, 'name' => $metadata->sellerName, 'identifier' => $metadata->sellerNip];
        }

        return [
            'nip' => 'Nip' === $metadata->buyerIdentifierType ? $metadata->buyerIdentifier : null,
            'name' => $metadata->buyerName,
            'identifier' => $metadata->buyerIdentifier,
        ];
    }
}
