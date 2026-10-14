<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\CostCenter;
use App\Entity\CropSeason;
use App\Enum\CostCenterType;
use App\Security\Permission;
use Doctrine\ORM\EntityManagerInterface;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IntegerField;
use EasyCorp\Bundle\EasyAdminBundle\Field\NumberField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Sezon dostaje własny ośrodek kosztów typu „pole/sezon” – na niego przypisuje się koszty uprawy.
 *
 * @extends AbstractCrudController<CropSeason>
 */
#[IsGranted(Permission::SETTINGS)]
final class CropSeasonCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return CropSeason::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('Sezon uprawy')
            ->setEntityLabelInPlural('Sezony upraw')
            ->setDefaultSort(['year' => 'DESC']);
    }

    public function createEntity(string $entityFqcn): CropSeason
    {
        return (new CropSeason())->setYear((int) date('Y'));
    }

    public function configureFields(string $pageName): iterable
    {
        yield AssociationField::new('field', 'Pole');
        yield IntegerField::new('year', 'Rok')->setThousandsSeparator('');
        yield TextField::new('crop', 'Uprawa');
        yield NumberField::new('areaHa', 'Powierzchnia (ha)')->setNumDecimals(2)->setStoredAsString()->setHelp('Puste = całe pole.');
        yield AssociationField::new('costCenter', 'Ośrodek kosztów')->hideOnForm();
    }

    public function persistEntity(EntityManagerInterface $entityManager, $entityInstance): void
    {
        $center = (new CostCenter())->setType(CostCenterType::FieldSeason)->setName($entityInstance->getLabel());
        $entityManager->persist($center);
        $entityInstance->setCostCenter($center);
        parent::persistEntity($entityManager, $entityInstance);
    }

    public function updateEntity(EntityManagerInterface $entityManager, $entityInstance): void
    {
        $entityInstance->getCostCenter()?->setName($entityInstance->getLabel());
        parent::updateEntity($entityManager, $entityInstance);
    }
}
