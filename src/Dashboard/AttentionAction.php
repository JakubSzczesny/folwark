<?php

declare(strict_types=1);

namespace App\Dashboard;

/**
 * Akcja w menu pod prawym przyciskiem na pozycji „Wymaga uwagi”: link, formularz POST z tokenem CSRF
 * albo „Uzupełnij w BS”.
 */
final readonly class AttentionAction
{
    private function __construct(
        public string $label,
        public string $url,
        public ?string $token,
        public ?string $icon,
        public ?string $confirm,
        public ?string $frame,
        public bool $bsPay = false,
    ) {
    }

    public static function link(string $label, string $url, ?string $icon = null, ?string $frame = null): self
    {
        return new self($label, $url, null, $icon, null, $frame);
    }

    public static function post(string $label, string $url, string $token, ?string $icon = null, ?string $confirm = null): self
    {
        return new self($label, $url, $token, $icon, $confirm, null);
    }

    /**
     * „Uzupełnij w BS” – $url to dane przelewu w JSON.
     */
    public static function bsPay(string $url): self
    {
        return new self('Uzupełnij w BS', $url, null, 'landmark', null, null, true);
    }

    public function isPost(): bool
    {
        return null !== $this->token;
    }
}
