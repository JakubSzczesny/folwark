<?php

declare(strict_types=1);

namespace App\Form;

use App\Entity\CostCategory;
use App\Entity\CostCenter;
use App\Repository\CostCategoryRepository;
use App\Repository\CostCenterRepository;
use App\Ui\RecentChoices;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;

/**
 * @extends AbstractType<array<string, mixed>>
 */
final class CostOnlyType extends AbstractType
{
    public function __construct(
        private readonly CostCenterRepository $centers,
        private readonly CostCategoryRepository $categories,
        private readonly RecentChoices $recent,
    ) {
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('costCenter', EntityType::class, [
                'label' => 'Ośrodek kosztów',
                'class' => CostCenter::class,
                'choices' => $this->recent->sort('cost_center', $this->centers->findActive()),
                'group_by' => static fn (CostCenter $c): string => $c->getType()->label(),
                'required' => false,
                'placeholder' => '— bez ośrodka —',
                'autocomplete' => true,
            ])
            ->add('costCategory', EntityType::class, [
                'label' => 'Kategoria kosztu',
                'class' => CostCategory::class,
                'choices' => $this->recent->sort('cost_category', $this->categories->findActive()),
                'required' => false,
                'placeholder' => '— bez kategorii —',
                'autocomplete' => true,
            ]);
    }
}
