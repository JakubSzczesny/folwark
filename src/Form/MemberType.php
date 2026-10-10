<?php

declare(strict_types=1);

namespace App\Form;

use App\Enum\UserRole;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\EnumType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Dodanie osoby do gospodarstwa. Gdy konto o tym adresie nie istnieje, można je od razu założyć.
 *
 * @extends AbstractType<array<string, mixed>>
 */
final class MemberType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('email', EmailType::class, [
                'label' => 'E-mail',
                'constraints' => [new Assert\NotBlank(), new Assert\Email()],
                'help' => 'Jeśli ta osoba ma już konto (np. sama się zarejestrowała), wystarczy e-mail.',
            ])
            ->add('role', EnumType::class, [
                'label' => 'Rola w gospodarstwie',
                'class' => UserRole::class,
                'choice_label' => static fn (UserRole $role): string => $role->label(),
                'expanded' => true,
                'data' => UserRole::Worker,
                'help' => 'Właściciel – wszystko. Pracownik – maszyny, zamówienia, towary (bez kwot faktur i analiz). Księgowa – odczyt faktur, kontrahentów, umów, dokumentów i eksporty.',
            ])
            ->add('fullName', TextType::class, [
                'label' => 'Imię i nazwisko (dla nowego konta)',
                'required' => false,
            ])
            ->add('password', PasswordType::class, [
                'label' => 'Hasło startowe (dla nowego konta)',
                'required' => false,
                'attr' => ['autocomplete' => 'new-password'],
                'help' => 'Min. 10 znaków. Osoba zmieni je potem w „Moje konto”.',
            ]);
    }
}
