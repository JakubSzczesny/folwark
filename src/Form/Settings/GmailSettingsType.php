<?php

declare(strict_types=1);

namespace App\Form\Settings;

use App\Settings\SettingKey;
use App\Settings\SettingsForm;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * @extends AbstractType<array<string, mixed>>
 */
final class GmailSettingsType extends AbstractType
{
    public const array KEYS = [SettingKey::GMAIL_ADDRESS];

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add(SettingsForm::field(SettingKey::GMAIL_ADDRESS), EmailType::class, [
                'label' => 'Adres skrzynki Gmail',
                'required' => false,
                'attr' => ['placeholder' => 'gospodarstwo@gmail.com', 'autocomplete' => 'off'],
                'constraints' => [new Assert\Email(), new Assert\Length(max: 180)],
            ])
            ->add('app_password', PasswordType::class, [
                'label' => 'Hasło do aplikacji',
                'mapped' => false,
                'required' => false,
                'help' => $options['password_set']
                    ? 'Hasło jest zapisane (zaszyfrowane). Wpisz nowe tylko, jeśli chcesz je zmienić.'
                    : '16 liter wygenerowanych w koncie Google – nie zwykłe hasło do Gmaila.',
                'attr' => ['autocomplete' => 'new-password', 'placeholder' => 'xxxx xxxx xxxx xxxx'],
                'constraints' => [new Assert\Regex(pattern: '/^[a-zA-Z\s]{16,24}$/', message: 'Hasło do aplikacji to 16 liter (spacje można zostawić).')],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['password_set' => false]);
    }
}
