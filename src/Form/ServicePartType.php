<?php

declare(strict_types=1);

namespace App\Form;

use App\Entity\MachineServicePart;
use App\Entity\Unit;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * @extends AbstractType<MachineServicePart>
 */
final class ServicePartType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('name', TextType::class, ['label' => 'Część', 'attr' => ['placeholder' => 'np. Filtr oleju RE504836']])
            ->add('quantity', NumberType::class, ['label' => 'Ilość', 'required' => false, 'scale' => 3, 'input' => 'string', 'html5' => true, 'attr' => ['step' => 'any']])
            ->add('unit', EntityType::class, ['label' => 'Jm.', 'class' => Unit::class, 'choice_label' => 'symbol', 'required' => false, 'placeholder' => '—']);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => MachineServicePart::class]);
    }
}
