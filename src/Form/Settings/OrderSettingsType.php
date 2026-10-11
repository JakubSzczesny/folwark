<?php

declare(strict_types=1);

namespace App\Form\Settings;

use App\Settings\SettingKey;
use App\Settings\SettingsForm;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Ustawienia przewidywania zamówień dla całego gospodarstwa.
 *
 * @extends AbstractType<array<string, mixed>>
 */
final class OrderSettingsType extends AbstractType
{
    public const array KEYS = [
        SettingKey::ORDERS_LEAD_DAYS,
        SettingKey::ORDERS_MIN_ORDERS,
        SettingKey::ORDERS_MERGE_DAYS,
        SettingKey::ORDERS_REGULAR_THRESHOLD,
        SettingKey::ORDERS_MODERATE_THRESHOLD,
        SettingKey::ORDERS_ORDERED_HOLD_DAYS,
    ];

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $ratio = ['scale' => 2, 'html5' => true, 'attr' => ['step' => '0.05'], 'constraints' => [new Assert\NotBlank(), new Assert\Range(min: 0.05, max: 2)]];

        $builder
            ->add(SettingsForm::field(SettingKey::ORDERS_LEAD_DAYS), IntegerType::class, [
                'label' => 'Wyprzedzenie przypomnienia (dni)',
                'help' => 'Przypomnienie przychodzi tyle dni (plus czas dostawy) przed przewidywaną datą.',
                'constraints' => [new Assert\NotBlank(), new Assert\Range(min: 0, max: 90)],
            ])
            ->add(SettingsForm::field(SettingKey::ORDERS_MIN_ORDERS), IntegerType::class, [
                'label' => 'Minimalna liczba zamówień',
                'help' => 'Mniej zamówień w ostatnich 24 miesiącach = „za mało danych”.',
                'constraints' => [new Assert\NotBlank(), new Assert\Range(min: 2, max: 20)],
            ])
            ->add(SettingsForm::field(SettingKey::ORDERS_MERGE_DAYS), IntegerType::class, [
                'label' => 'Łączenie zakupów (dni)',
                'help' => 'Zakupy w takim odstępie liczą się jako jedno zamówienie.',
                'constraints' => [new Assert\NotBlank(), new Assert\Range(min: 0, max: 14)],
            ])
            ->add(SettingsForm::field(SettingKey::ORDERS_REGULAR_THRESHOLD), NumberType::class, ['label' => 'Próg „regularny” (rozrzut / odstęp)'] + $ratio)
            ->add(SettingsForm::field(SettingKey::ORDERS_MODERATE_THRESHOLD), NumberType::class, ['label' => 'Próg „umiarkowanie regularny”', 'help' => 'Powyżej – nieregularny, bez przypomnień.'] + $ratio)
            ->add(SettingsForm::field(SettingKey::ORDERS_ORDERED_HOLD_DAYS), IntegerType::class, [
                'label' => '„Zamówione” wstrzymuje przypomnienia na (dni)',
                'help' => 'Albo do nadejścia faktury.',
                'constraints' => [new Assert\NotBlank(), new Assert\Range(min: 1, max: 120)],
            ]);
    }
}
