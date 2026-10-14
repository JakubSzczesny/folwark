<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\Warehouse;
use App\Enum\WarehouseType;
use App\Security\Permission;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField;
use EasyCorp\Bundle\EasyAdminBundle\Field\NumberField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * @extends AbstractCrudController<Warehouse>
 */
#[IsGranted(Permission::SETTINGS)]
final class WarehouseCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return Warehouse::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('Magazyn')
            ->setEntityLabelInPlural('Magazyny')
            ->setDefaultSort(['name' => 'ASC']);
    }

    public function configureFields(string $pageName): iterable
    {
        $types = [];
        foreach (WarehouseType::cases() as $type) {
            $types[$type->label()] = $type;
        }

        yield TextField::new('name', 'Nazwa');
        yield ChoiceField::new('type', 'Typ')->setChoices($types)->formatValue(static fn ($value, $entity): string => $entity->getType()->label());
        yield NumberField::new('capacity', 'Pojemność')->setNumDecimals(0)->setStoredAsString();
        yield AssociationField::new('capacityUnit', 'Jednostka pojemności');
        yield BooleanField::new('active', 'Aktywny');
    }
}
