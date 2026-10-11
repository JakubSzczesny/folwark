<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\ServiceType;
use App\Security\Permission;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IntegerField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * @extends AbstractCrudController<ServiceType>
 */
#[IsGranted(Permission::SETTINGS)]
final class ServiceTypeCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return ServiceType::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('Rodzaj serwisu')
            ->setEntityLabelInPlural('Rodzaje serwisu')
            ->setDefaultSort(['position' => 'ASC', 'name' => 'ASC']);
    }

    public function configureFields(string $pageName): iterable
    {
        yield TextField::new('name', 'Nazwa');
        yield IntegerField::new('position', 'Kolejność');
        yield BooleanField::new('active', 'Aktywny')->setHelp('Nieaktywnego nie da się wybrać w nowych wpisach; stare zostają.');
    }
}
