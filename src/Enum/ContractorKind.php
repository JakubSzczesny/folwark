<?php

declare(strict_types=1);

namespace App\Enum;

enum ContractorKind: string
{
    case Supplier = 'supplier';
    case Customer = 'customer';
    case Both = 'both';

    public function label(): string
    {
        return match ($this) {
            self::Supplier => 'Dostawca',
            self::Customer => 'Odbiorca',
            self::Both => 'Dostawca i odbiorca',
        };
    }

    public function with(InvoiceKind $kind): self
    {
        $add = InvoiceKind::Cost === $kind ? self::Supplier : self::Customer;

        return $this === $add ? $this : self::Both;
    }
}
