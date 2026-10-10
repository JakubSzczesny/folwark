<?php

declare(strict_types=1);

namespace App\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\RepeatedType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Security\Core\Validator\Constraints\UserPassword;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * @extends AbstractType<array<string, mixed>>
 */
final class ChangePasswordType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('currentPassword', PasswordType::class, [
                'label' => 'Obecne hasło',
                'constraints' => [new UserPassword(message: 'Obecne hasło jest nieprawidłowe.')],
                'attr' => ['autocomplete' => 'current-password'],
            ])
            ->add('newPassword', RepeatedType::class, [
                'type' => PasswordType::class,
                'first_options' => ['label' => 'Nowe hasło', 'attr' => ['autocomplete' => 'new-password']],
                'second_options' => ['label' => 'Powtórz nowe hasło', 'attr' => ['autocomplete' => 'new-password']],
                'invalid_message' => 'Hasła muszą być takie same.',
                'constraints' => [
                    new Assert\NotBlank(),
                    new Assert\Length(min: 10, minMessage: 'Hasło musi mieć co najmniej {{ limit }} znaków.'),
                ],
            ]);
    }
}
