<?php

declare(strict_types=1);

namespace App\Dashboard;

final readonly class AttentionItem
{
    /**
     * @param int                   $urgency im mniejsza wartość, tym wyżej na liście (np. liczba dni do terminu)
     * @param list<AttentionAction> $actions akcje w menu pod prawym przyciskiem
     */
    public function __construct(
        public int $urgency,
        public string $tone,
        public string $category,
        public string $title,
        public string $url,
        public ?string $detail = null,
        public array $actions = [],
    ) {
    }
}
