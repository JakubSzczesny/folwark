<?php

declare(strict_types=1);

namespace App\Enum;

enum InvoiceType: string
{
    case Invoice = 'invoice';
    case Correction = 'correction';
    case Advance = 'advance';
    case Settlement = 'settlement';
    case Rr = 'rr';

    public function label(): string
    {
        return match ($this) {
            self::Invoice => 'Faktura',
            self::Correction => 'Korekta',
            self::Advance => 'Zaliczkowa',
            self::Settlement => 'Rozliczeniowa',
            self::Rr => 'VAT RR',
        };
    }

    /**
     * Rodzaj faktury z KSeF (InvoiceType w API) albo z pola RodzajFaktury w XML.
     */
    public static function fromKsef(string $code): self
    {
        return match (strtoupper(str_replace('_', '', $code))) {
            'KOR', 'KORZAL', 'KORROZ', 'KORPEF', 'KORVATRR' => self::Correction,
            'ZAL' => self::Advance,
            'ROZ' => self::Settlement,
            'VATRR' => self::Rr,
            default => self::Invoice,
        };
    }
}
