<?php

declare(strict_types=1);

namespace App\Enum;

enum ContractKind: string
{
    case Sale = 'sale';
    case Purchase = 'purchase';
    case Service = 'service';
    case Lease = 'lease';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Sale => 'Sprzedaż',
            self::Purchase => 'Zakup',
            self::Service => 'Usługa',
            self::Lease => 'Dzierżawa',
            self::Other => 'Inna',
        };
    }

    /**
     * Rodzaj umowy pasujący do faktury: sprzedażowa – sprzedaż, zakupowa – zakup.
     */
    public static function forInvoice(InvoiceKind $kind): self
    {
        return InvoiceKind::Income === $kind ? self::Sale : self::Purchase;
    }
}
