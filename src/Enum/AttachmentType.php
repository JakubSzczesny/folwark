<?php

declare(strict_types=1);

namespace App\Enum;

enum AttachmentType: string
{
    case Photo = 'photo';
    case File = 'file';
    case Link = 'link';

    public function label(): string
    {
        return match ($this) {
            self::Photo => 'Zdjęcie',
            self::File => 'Plik',
            self::Link => 'Link',
        };
    }
}
