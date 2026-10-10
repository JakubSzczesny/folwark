<?php

declare(strict_types=1);

namespace App\Form;

use App\Entity\WeighbridgeTask;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * @extends AbstractType<WeighbridgeTask>
 */
final class WeighbridgeTaskType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('groupName', TextType::class, [
                'label' => 'Grupa',
                'attr' => ['list' => 'weighbridge-groups'],
            ])
            ->add('content', TextareaType::class, ['label' => 'Treść'])
            ->add('position', IntegerType::class, ['label' => 'Kolejność'])
            ->add('note', TextareaType::class, ['label' => 'Notatka', 'required' => false]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => WeighbridgeTask::class]);
    }
}
