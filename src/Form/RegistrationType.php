<?php

declare(strict_types=1);

namespace App\Form;

use App\Entity\User;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\RepeatedType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * @extends AbstractType<User>
 */
final class RegistrationType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('fullName', TextType::class, ['label' => 'Imię i nazwisko', 'attr' => ['autocomplete' => 'name', 'autofocus' => true]])
            ->add('email', EmailType::class, ['label' => 'E-mail', 'attr' => ['autocomplete' => 'email']])
            ->add('plainPassword', RepeatedType::class, [
                'type' => PasswordType::class,
                'mapped' => false,
                'first_options' => ['label' => 'Hasło', 'attr' => ['autocomplete' => 'new-password']],
                'second_options' => ['label' => 'Powtórz hasło', 'attr' => ['autocomplete' => 'new-password']],
                'invalid_message' => 'Hasła muszą być takie same.',
                'constraints' => [
                    new Assert\NotBlank(),
                    new Assert\Length(min: 10, minMessage: 'Hasło musi mieć co najmniej {{ limit }} znaków.'),
                ],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => User::class]);
    }
}
