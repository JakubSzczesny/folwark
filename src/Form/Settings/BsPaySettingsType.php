<?php

declare(strict_types=1);

namespace App\Form\Settings;

use App\Settings\SettingKey;
use App\Settings\SettingsForm;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\UrlType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * @extends AbstractType<array<string, mixed>>
 */
final class BsPaySettingsType extends AbstractType
{
    public const array KEYS = [
        SettingKey::BSPAY_ENABLED,
        SettingKey::BSPAY_BANK_URL,
        SettingKey::BSPAY_TITLE,
        SettingKey::BSPAY_SPLIT_PAYMENT,
    ];

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add(SettingsForm::field(SettingKey::BSPAY_ENABLED), CheckboxType::class, [
                'label' => 'Włącz BS Pay – przycisk „Przelew w BS” przy fakturach do zapłaty',
                'required' => false,
            ])
            ->add(SettingsForm::field(SettingKey::BSPAY_BANK_URL), UrlType::class, [
                'label' => 'Adres bankowości internetowej BS Bydgoszcz',
                'required' => false,
                'default_protocol' => 'https',
                'help' => 'Strona logowania albo formularza przelewu, otwierana w nowej karcie. Skopiuj ją z paska adresu po zalogowaniu.',
                'constraints' => [new Assert\Url(requireTld: true), new Assert\Regex(pattern: '~^https://~', message: 'Adres banku musi zaczynać się od https://.')],
            ])
            ->add(SettingsForm::field(SettingKey::BSPAY_TITLE), TextType::class, [
                'label' => 'Tytuł przelewu',
                'help' => '{numer} – numer faktury, {data} – data wystawienia, {nip} – NIP odbiorcy.',
                'constraints' => [new Assert\NotBlank(), new Assert\Length(max: 140)],
            ])
            ->add(SettingsForm::field(SettingKey::BSPAY_SPLIT_PAYMENT), CheckboxType::class, [
                'label' => 'Faktury z adnotacją „mechanizm podzielonej płatności” przygotowuj jako przelew MPP',
                'required' => false,
            ]);
    }
}
