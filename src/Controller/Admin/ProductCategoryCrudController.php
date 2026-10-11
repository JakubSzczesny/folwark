<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\ProductCategory;
use App\Security\Permission;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IntegerField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * @extends AbstractCrudController<ProductCategory>
 */
#[IsGranted(Permission::SETTINGS)]
final class ProductCategoryCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return ProductCategory::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('Kategoria towarów')
            ->setEntityLabelInPlural('Kategorie towarów')
            ->setDefaultSort(['position' => 'ASC', 'name' => 'ASC']);
    }

    public function configureFields(string $pageName): iterable
    {
        yield TextField::new('name', 'Nazwa');
        yield AssociationField::new('parent', 'Kategoria nadrzędna')->setRequired(false)->setHelp('Puste = kategoria główna.');
        yield IntegerField::new('position', 'Kolejność');
    }
}
