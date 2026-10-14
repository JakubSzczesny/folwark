<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\Field;
use App\Security\Permission;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;
use EasyCorp\Bundle\EasyAdminBundle\Field\NumberField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextareaField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * @extends AbstractCrudController<Field>
 */
#[IsGranted(Permission::SETTINGS)]
final class FieldCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return Field::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('Pole')
            ->setEntityLabelInPlural('Pola')
            ->setDefaultSort(['name' => 'ASC']);
    }

    public function configureFields(string $pageName): iterable
    {
        yield TextField::new('name', 'Nazwa');
        yield NumberField::new('areaHa', 'Powierzchnia (ha)')->setNumDecimals(2)->setStoredAsString();
        yield TextField::new('parcelNumbers', 'Numery działek');
        yield TextareaField::new('notes', 'Uwagi')->hideOnIndex();
        yield BooleanField::new('active', 'Aktywne');
    }
}
