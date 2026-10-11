<?php

declare(strict_types=1);

namespace App\Form;

use App\Entity\ProductUnitConversion;
use App\Entity\Unit;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * @extends AbstractType<ProductUnitConversion>
 */
final class ProductUnitConversionType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('unit', EntityType::class, ['label' => '1', 'class' => Unit::class, 'choice_label' => 'symbol', 'placeholder' => 'jednostka'])
            ->add('factor', NumberType::class, ['label' => '=', 'scale' => 6, 'input' => 'string', 'html5' => true, 'attr' => ['step' => 'any', 'placeholder' => 'np. 450']]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => ProductUnitConversion::class]);
    }
}
