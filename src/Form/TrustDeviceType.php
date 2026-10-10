<?php

declare(strict_types=1);

namespace App\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\RepeatedType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * @extends AbstractType<array<string, mixed>>
 */
final class TrustDeviceType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('name', TextType::class, [
                'label' => 'Nazwa urządzenia',
                'help' => 'Np. „Telefon Jana” – żeby rozpoznać je na liście.',
                'constraints' => [new Assert\NotBlank(), new Assert\Length(max: 100)],
            ])
            ->add('pin', RepeatedType::class, [
                'type' => PasswordType::class,
                'first_options' => ['label' => 'PIN (4–6 cyfr)', 'attr' => ['inputmode' => 'numeric', 'autocomplete' => 'off', 'maxlength' => 6]],
                'second_options' => ['label' => 'Powtórz PIN', 'attr' => ['inputmode' => 'numeric', 'autocomplete' => 'off', 'maxlength' => 6]],
                'invalid_message' => 'PIN-y muszą być takie same.',
                'constraints' => [
                    new Assert\NotBlank(),
                    new Assert\Regex(pattern: '/^\d{4,6}$/', message: 'PIN musi mieć od 4 do 6 cyfr.'),
                ],
            ]);
    }
}
