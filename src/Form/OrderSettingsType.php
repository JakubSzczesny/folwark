<?php

declare(strict_types=1);

namespace App\Form;

use App\Entity\OrderSettings;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * @extends AbstractType<OrderSettings>
 */
final class OrderSettingsType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('manualIntervalDays', IntegerType::class, ['label' => 'Własny odstęp (dni)', 'required' => false, 'help' => 'Np. 21 = „co 3 tygodnie”. Puste = wyliczany z historii.'])
            ->add('manualQuantity', NumberType::class, ['label' => 'Własna typowa ilość', 'required' => false, 'scale' => 3, 'input' => 'string', 'html5' => true, 'attr' => ['step' => 'any']])
            ->add('leadDays', IntegerType::class, ['label' => 'Przypomnij dni wcześniej', 'required' => false, 'help' => 'Puste = jak w ustawieniach gospodarstwa (plus czas dostawy).'])
            ->add('seasonalDisabled', CheckboxType::class, ['label' => 'Wyłącz tryb sezonowy', 'required' => false])
            ->add('hidden', CheckboxType::class, ['label' => 'Ukryj na liście zamówień', 'required' => false, 'help' => 'Bez przypomnień – np. dla rzeczy kupowanych okazjonalnie.']);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => OrderSettings::class]);
    }
}
