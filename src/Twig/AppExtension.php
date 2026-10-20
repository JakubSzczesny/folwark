<?php

declare(strict_types=1);

namespace App\Twig;

use App\Entity\Farm;
use App\Entity\FarmMembership;
use App\Entity\User;
use App\Enum\UserRole;
use App\Farm\FarmContext;
use App\Module\Modules;
use App\Repository\FarmMembershipRepository;
use App\Ui\Navigation;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Routing\Exception\RouteNotFoundException;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Twig\Attribute\AsTwigFunction;

final class AppExtension
{
    /** @var list<FarmMembership>|null */
    private ?array $memberships = null;

    public function __construct(
        private readonly Navigation $navigation,
        private readonly FarmContext $farmContext,
        private readonly FarmMembershipRepository $membershipRepository,
        private readonly Security $security,
        private readonly UrlGeneratorInterface $urls,
        private readonly Modules $modules,
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

    #[AsTwigFunction('current_farm')]
    public function currentFarm(): ?Farm
    {
        return $this->farmContext->getFarm();
    }

    #[AsTwigFunction('current_role')]
    public function currentRole(): ?UserRole
    {
        return $this->farmContext->getRole();
    }

    #[AsTwigFunction('farm_name')]
    public function farmName(): string
    {
        return $this->farmContext->getFarm()?->getName() ?? 'Folwark';
    }

    /**
     * Gospodarstwa zalogowanego użytkownika – do przełącznika w górnym pasku.
     *
     * @return list<FarmMembership>
     */
    #[AsTwigFunction('user_memberships')]
    public function userMemberships(): array
    {
        $user = $this->security->getUser();
        if (!$user instanceof User) {
            return [];
        }

        return $this->memberships ??= $this->membershipRepository->findForUser($user);
    }

    #[AsTwigFunction('module_enabled')]
    public function moduleEnabled(string $key): bool
    {
        return $this->modules->isEnabled($key);
    }

    #[AsTwigFunction('module_label')]
    public function moduleLabel(string $key): string
    {
        return Modules::label($key);
    }

    /**
     * Pozwala szablonom pokazywać akcje modułów, które mogą jeszcze nie istnieć albo są wyłączone.
     */
    #[AsTwigFunction('route_exists')]
    public function routeExists(string $name): bool
    {
        if (null !== $this->modules->disabledModuleOfRoute($name)) {
            return false;
        }

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
