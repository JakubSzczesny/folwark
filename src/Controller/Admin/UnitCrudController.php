<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\Unit;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;

/**
 * @extends AbstractCrudController<Unit>
 */
final class UnitCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return Unit::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('Jednostka')
            ->setEntityLabelInPlural('Jednostki')
            ->setDefaultSort(['symbol' => 'ASC']);
    }

    public function configureFields(string $pageName): iterable
    {
        yield TextField::new('symbol', 'Symbol');
        yield TextField::new('name', 'Nazwa');
    }
}
