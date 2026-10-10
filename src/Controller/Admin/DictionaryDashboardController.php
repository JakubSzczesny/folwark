<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\CostCategory;
use App\Entity\CostCenter;
use App\Entity\Unit;
use App\Security\Permission;
use EasyCorp\Bundle\EasyAdminBundle\Attribute\AdminDashboard;
use EasyCorp\Bundle\EasyAdminBundle\Config\Assets;
use EasyCorp\Bundle\EasyAdminBundle\Config\Dashboard;
use EasyCorp\Bundle\EasyAdminBundle\Config\MenuItem;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractDashboardController;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGenerator;
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
    ) {
    }

    public function index(): Response
    {
        return $this->redirect($this->urls->setController(CostCategoryCrudController::class)->generateUrl());
    }

    public function configureDashboard(): Dashboard
    {
        return Dashboard::new()
            ->setTitle('Folwark · słowniki')
            ->setLocales(['pl'])
            ->disableDarkMode();
    }

    public function configureAssets(): Assets
    {
        return Assets::new()->addHtmlContentToHead('<style>:root{--color-primary:#2E7D46;--link-color:#2E7D46;--sidebar-bg:#24282C;}</style>');
    }

    public function configureMenuItems(): iterable
    {
        yield MenuItem::linkToUrl('Wróć do ustawień', 'fa fa-arrow-left', '/ustawienia');
        yield MenuItem::section('Faktury i koszty');
        yield MenuItem::linkToCrud('Kategorie kosztów', 'fa fa-tags', CostCategory::class);
        yield MenuItem::linkToCrud('Ośrodki kosztów', 'fa fa-sitemap', CostCenter::class);
        yield MenuItem::section('Magazyn');
        yield MenuItem::linkToCrud('Jednostki', 'fa fa-ruler', Unit::class);
    }
}
