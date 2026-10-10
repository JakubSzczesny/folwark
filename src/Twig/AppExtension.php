<?php

declare(strict_types=1);

namespace App\Twig;

use App\Settings\SettingKey;
use App\Settings\Settings;
use App\Ui\Navigation;
use Twig\Attribute\AsTwigFunction;

final class AppExtension
{
    public function __construct(
        private readonly Navigation $navigation,
        private readonly Settings $settings,
    ) {
    }

    /**
     * @return list<array<string, mixed>>
     */
    #[AsTwigFunction('app_menu')]
    public function menu(): array
    {
        return $this->navigation->items();
    }

    /**
     * @return list<array<string, mixed>>
     */
    #[AsTwigFunction('app_mobile_menu')]
    public function mobileMenu(): array
    {
        return $this->navigation->mobileItems();
    }

    #[AsTwigFunction('farm_name')]
    public function farmName(): string
    {
        return $this->settings->string(SettingKey::FARM_NAME) ?? 'Gospodarstwo';
    }
}
