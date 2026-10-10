<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\CostCategory;
use App\Security\Permission;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * @extends AbstractCrudController<CostCategory>
 */
#[IsGranted(Permission::SETTINGS)]
final class CostCategoryCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return CostCategory::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('Kategoria kosztu')
            ->setEntityLabelInPlural('Kategorie kosztów')
            ->setDefaultSort(['name' => 'ASC']);
    }

    public function configureFields(string $pageName): iterable
    {
        yield TextField::new('name', 'Nazwa');
        yield BooleanField::new('active', 'Aktywna');
    }
}
