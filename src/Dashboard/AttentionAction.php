<?php

declare(strict_types=1);

namespace App\Dashboard;

/**
 * Akcja w menu pod prawym przyciskiem na pozycji „Wymaga uwagi”: link albo formularz POST z tokenem CSRF.
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

    public function isPost(): bool
    {
        return null !== $this->token;
    }
}
