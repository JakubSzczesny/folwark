<?php

declare(strict_types=1);

namespace App\Twig;

use App\Controller\PaymentController;
use App\Settings\SettingKey;
use App\Settings\Settings;
use Twig\Attribute\AsTwigFunction;

final class BsPayExtension
{
    public function __construct(
        private readonly Settings $settings,
    ) {
    }

    #[AsTwigFunction('bspay_enabled')]
    public function enabled(): bool
    {
        return $this->settings->bool(SettingKey::BSPAY_ENABLED);
    }

    #[AsTwigFunction('bspay_bank_url')]
    public function bankUrl(): string
    {
        return $this->settings->string(SettingKey::BSPAY_BANK_URL) ?: PaymentController::DEFAULT_BANK_URL;
    }
}
