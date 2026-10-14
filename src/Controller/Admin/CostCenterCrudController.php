<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\CostCenter;
use App\Enum\CostCenterType;
use App\Security\Permission;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * @extends AbstractCrudController<CostCenter>
 */
#[IsGranted(Permission::SETTINGS)]
final class CostCenterCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return CostCenter::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('Ośrodek kosztów')
            ->setEntityLabelInPlural('Ośrodki kosztów')
            ->setDefaultSort(['type' => 'ASC', 'name' => 'ASC']);
    }

    public function configureFields(string $pageName): iterable
    {
        $types = [];
        foreach (CostCenterType::cases() as $type) {
            $types[$type->label()] = $type;
        }

        yield ChoiceField::new('type', 'Typ')->setChoices($types)->formatValue(static fn ($value, $entity): string => $entity->getType()->label());
        yield TextField::new('name', 'Nazwa');
        yield BooleanField::new('active', 'Aktywny');
    }
}
