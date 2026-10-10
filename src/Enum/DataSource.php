<?php

declare(strict_types=1);

namespace App\Enum;

enum DataSource: string
{
    case Ksef = 'ksef';
    case KsefXml = 'ksef_xml';
    case WhiteList = 'white_list';
    case Manual = 'manual';

    public function label(): string
    {
        return match ($this) {
            self::Ksef => 'KSeF (metadane)',
            self::KsefXml => 'KSeF (faktura XML)',
            self::WhiteList => 'Biała lista VAT',
            self::Manual => 'Ręcznie',
        };
    }
}
