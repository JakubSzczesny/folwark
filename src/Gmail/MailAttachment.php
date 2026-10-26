<?php

declare(strict_types=1);

namespace App\Gmail;

final readonly class MailAttachment
{
    /** Typy, które przeglądarka pokazuje bezpiecznie w nowej karcie zamiast pobierać. */
    private const array PREVIEWABLE = ['application/pdf', 'image/png', 'image/jpeg', 'image/gif', 'image/webp', 'text/plain'];

    public function __construct(
        public int $index,
        public string $name,
        public string $mimeType,
        public int $size,
        public string $content,
    ) {
    }

    public function isPreviewable(): bool
    {
        return \in_array($this->mimeType, self::PREVIEWABLE, true);
    }
}
