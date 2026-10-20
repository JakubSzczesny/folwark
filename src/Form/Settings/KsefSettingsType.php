<?php

declare(strict_types=1);

namespace App\Form\Settings;

use App\Ksef\Http\KsefEnvironment;
use App\Settings\SettingKey;
use App\Settings\SettingsForm;
use App\Validator\ValidNip;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * @extends AbstractType<array<string, mixed>>
 */
final class KsefSettingsType extends AbstractType
{
    public const array KEYS = [
        SettingKey::KSEF_NIP,
        SettingKey::KSEF_ENVIRONMENT,
        SettingKey::KSEF_SYNC_INTERVAL,
        SettingKey::KSEF_AUTO_FETCH_XML,
        SettingKey::KSEF_AUTO_ASSIGN,
        SettingKey::KSEF_SYNC_FROM,
        SettingKey::WHITE_LIST_ENABLED,
    ];

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $environments = [];
        foreach (KsefEnvironment::cases() as $environment) {
            $environments[$environment->label()] = $environment->value;
        }

        $builder
            ->add('token', PasswordType::class, [
                'label' => 'Token KSeF',
                'mapped' => false,
                'required' => false,
                'help' => $options['token_set']
                    ? 'Token jest zapisany (zaszyfrowany). Wpisz nowy tylko, jeśli chcesz go zmienić.'
                    : 'Token z uprawnieniem wyłącznie do przeglądania faktur (wygeneruj go w aplikacji MF).',
                'attr' => ['autocomplete' => 'off'],
            ])
            ->add(SettingsForm::field(SettingKey::KSEF_NIP), TextType::class, [
                'label' => 'NIP (kontekst KSeF)',
                'required' => false,
                'help' => 'Puste = NIP gospodarstwa z ustawień ogólnych.',
                'constraints' => [new ValidNip()],
            ])
            ->add(SettingsForm::field(SettingKey::KSEF_ENVIRONMENT), ChoiceType::class, [
                'label' => 'Środowisko',
                'choices' => $environments,
                'help' => 'Zacznij od środowiska testowego.',
            ])
            ->add(SettingsForm::field(SettingKey::KSEF_SYNC_INTERVAL), IntegerType::class, [
                'label' => 'Synchronizacja co (minut)',
                'constraints' => [new Assert\Range(min: 15, max: 1440)],
                'help' => 'KSeF pozwala na 20 zapytań o metadane na godzinę; jedna synchronizacja to co najmniej 2 zapytania.',
            ])
            ->add(SettingsForm::field(SettingKey::KSEF_SYNC_FROM), DateType::class, [
                'label' => 'Pobieraj faktury od dnia',
                'widget' => 'single_text',
                'input' => 'string',
                'input_format' => 'Y-m-d',
                'help' => 'Zakres pierwszej synchronizacji (historia potrzebna do przewidywania zamówień).',
            ])
            ->add(SettingsForm::field(SettingKey::KSEF_AUTO_FETCH_XML), CheckboxType::class, [
                'label' => 'Automatycznie pobieraj pełny XML nowych faktur zakupowych',
                'required' => false,
                'help' => 'Bez XML nie ma pozycji faktury, a więc przewidywania zamówień na poziomie towaru.',
            ])
            ->add(SettingsForm::field(SettingKey::KSEF_AUTO_ASSIGN), CheckboxType::class, [
                'label' => 'Automatycznie przypisuj towary z zapamiętanych mapowań',
                'required' => false,
            ])
            ->add(SettingsForm::field(SettingKey::WHITE_LIST_ENABLED), CheckboxType::class, [
                'label' => 'Uzupełniaj dane kontrahentów z Białej listy VAT',
                'required' => false,
                'help' => 'Gdy faktura nie podaje nazwy lub adresu. Wyniki są zapamiętywane, liczba zapytań dziennie jest ograniczona.',
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['token_set' => false]);
    }
}
