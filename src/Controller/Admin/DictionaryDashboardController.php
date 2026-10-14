<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\CostCategory;
use App\Entity\CostCenter;
use App\Entity\CropSeason;
use App\Entity\Field;
use App\Entity\MachineCategory;
use App\Entity\ProductCategory;
use App\Entity\ServiceType;
use App\Entity\Unit;
use App\Entity\Warehouse;
use App\Security\Permission;
use EasyCorp\Bundle\EasyAdminBundle\Attribute\AdminDashboard;
use EasyCorp\Bundle\EasyAdminBundle\Config\Assets;
use EasyCorp\Bundle\EasyAdminBundle\Config\Dashboard;
use EasyCorp\Bundle\EasyAdminBundle\Config\MenuItem;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractDashboardController;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGenerator;
use Symfony\Component\Asset\Packages;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Proste słowniki edytowane przez EasyAdmin. Ekrany główne aplikacji są budowane ręcznie.
 */
#[AdminDashboard(routePath: '/ustawienia/slowniki', routeName: 'admin')]
#[IsGranted(Permission::SETTINGS)]
final class DictionaryDashboardController extends AbstractDashboardController
{
    public function __construct(
        private readonly AdminUrlGenerator $urls,
        private readonly Packages $assets,
    ) {
    }

    public function index(): Response
    {
        return $this->redirect($this->urls->setController(CostCategoryCrudController::class)->generateUrl());
    }

    public function configureDashboard(): Dashboard
    {
        return Dashboard::new()
            ->setTitle(\sprintf('<span class="folwark-brand"><img src="%s" width="28" height="28" alt=""> Słowniki</span>', $this->assets->getUrl('images/folwark.svg')))
            ->setFaviconPath($this->assets->getUrl('images/folwark.svg'))
            ->setLocales(['pl'])
            ->disableDarkMode();
    }

    public function configureAssets(): Assets
    {
        return Assets::new()->addCssFile('styles/admin.css');
    }

    public function configureMenuItems(): iterable
    {
        yield MenuItem::linkToUrl('Wróć do ustawień', 'fa fa-arrow-left', '/ustawienia');
        yield MenuItem::section('Faktury i koszty');
        yield MenuItem::linkToCrud('Kategorie kosztów', 'fa fa-tags', CostCategory::class);
        yield MenuItem::linkToCrud('Ośrodki kosztów', 'fa fa-sitemap', CostCenter::class);
        yield MenuItem::section('Maszyny');
        yield MenuItem::linkToCrud('Kategorie maszyn', 'fa fa-tractor', MachineCategory::class);
        yield MenuItem::linkToCrud('Rodzaje serwisu', 'fa fa-wrench', ServiceType::class);
        yield MenuItem::section('Magazyn');
        yield MenuItem::linkToCrud('Kategorie towarów', 'fa fa-boxes-stacked', ProductCategory::class);
        yield MenuItem::linkToCrud('Jednostki', 'fa fa-ruler', Unit::class);
        yield MenuItem::linkToCrud('Magazyny', 'fa fa-warehouse', Warehouse::class);
        yield MenuItem::section('Pola');
        yield MenuItem::linkToCrud('Pola', 'fa fa-map', Field::class);
        yield MenuItem::linkToCrud('Sezony upraw', 'fa fa-seedling', CropSeason::class);
    }
}
