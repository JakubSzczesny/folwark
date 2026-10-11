<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\MachineCategory;
use App\Security\Permission;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\IntegerField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * @extends AbstractCrudController<MachineCategory>
 */
#[IsGranted(Permission::SETTINGS)]
final class MachineCategoryCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return MachineCategory::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('Kategoria maszyn')
            ->setEntityLabelInPlural('Kategorie maszyn')
            ->setDefaultSort(['position' => 'ASC', 'name' => 'ASC']);
    }

    public function configureFields(string $pageName): iterable
    {
        yield TextField::new('name', 'Nazwa');
        yield IntegerField::new('position', 'Kolejność')->setHelp('Kolejność zakładek na liście maszyn.');
    }
}
