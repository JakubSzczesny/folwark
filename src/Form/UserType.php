<?php

declare(strict_types=1);

namespace App\Form;

use App\Entity\User;
use App\Enum\UserRole;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\EnumType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * @extends AbstractType<User>
 */
final class UserType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('fullName', TextType::class, ['label' => 'Imię i nazwisko'])
            ->add('email', EmailType::class, ['label' => 'E-mail (login)'])
            ->add('role', EnumType::class, [
                'label' => 'Rola',
                'class' => UserRole::class,
                'choice_label' => static fn (UserRole $role): string => $role->label(),
                'expanded' => true,
                'help' => 'Właściciel – wszystko. Pracownik – maszyny, zamówienia, towary (bez kwot faktur i analiz). Księgowa – odczyt faktur, kontrahentów, umów, dokumentów i eksporty.',
            ])
            ->add('active', CheckboxType::class, ['label' => 'Konto aktywne', 'required' => false])
            ->add('plainPassword', PasswordType::class, [
                'label' => $options['require_password'] ? 'Hasło' : 'Nowe hasło (zostaw puste, by nie zmieniać)',
                'mapped' => false,
                'required' => $options['require_password'],
                'attr' => ['autocomplete' => 'new-password'],
                'constraints' => array_filter([
                    $options['require_password'] ? new Assert\NotBlank() : null,
                    new Assert\Length(min: 10, minMessage: 'Hasło musi mieć co najmniej {{ limit }} znaków.'),
                ]),
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => User::class,
            'require_password' => false,
        ]);
        $resolver->setAllowedTypes('require_password', 'bool');
    }
}
