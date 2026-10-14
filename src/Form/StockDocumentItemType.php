<?php

declare(strict_types=1);

namespace App\Form;

use App\Entity\StockDocumentItem;
use App\Entity\Unit;
use App\Form\Field\ProductAutocompleteField;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * @extends AbstractType<StockDocumentItem>
 */
final class StockDocumentItemType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $number = ['input' => 'string', 'html5' => true, 'attr' => ['step' => 'any', 'min' => 0]];

        $builder
            ->add('product', ProductAutocompleteField::class, ['label' => 'Towar'])
            ->add('enteredQuantity', NumberType::class, ['label' => $options['inventory'] ? 'Stan ze spisu' : 'Ilość', 'scale' => 4] + $number)
            ->add('enteredUnit', EntityType::class, [
                'label' => 'Jm.',
                'class' => Unit::class,
                'choice_label' => 'symbol',
                'required' => false,
                'placeholder' => 'podst.',
            ]);

        if ($options['with_price']) {
            $builder->add('unitPriceNet', NumberType::class, ['label' => 'Cena netto', 'required' => false, 'scale' => 4] + $number);
        }
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => StockDocumentItem::class,
            'with_price' => false,
            'inventory' => false,
        ]);
        $resolver->setAllowedTypes('with_price', 'bool');
        $resolver->setAllowedTypes('inventory', 'bool');
    }
}
