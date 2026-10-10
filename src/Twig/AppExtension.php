<?php

declare(strict_types=1);

namespace App\Twig;

use App\Settings\SettingKey;
use App\Settings\Settings;
use App\Ui\Navigation;
use Symfony\Component\Routing\Exception\RouteNotFoundException;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Twig\Attribute\AsTwigFunction;

final class AppExtension
{
    public function __construct(
        private readonly Navigation $navigation,
        private readonly Settings $settings,
        private readonly UrlGeneratorInterface $urls,
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

    #[AsTwigFunction('farm_nip')]
    public function farmNip(): ?string
    {
        return $this->settings->string(SettingKey::FARM_NIP);
    }

    /**
     * Pozwala szablonom pokazywać akcje modułów, które mogą jeszcze nie istnieć.
     */
    #[AsTwigFunction('route_exists')]
    public function routeExists(string $name): bool
    {
        try {
            $this->urls->generate($name, ['id' => 0]);

            return true;
        } catch (RouteNotFoundException) {
            return false;
        } catch (\Throwable) {
            return true;
        }
    }
}
