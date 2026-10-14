<?php

declare(strict_types=1);

namespace App\Form\Settings;

use App\Settings\SettingKey;
use App\Settings\SettingsForm;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * @extends AbstractType<array<string, mixed>>
 */
final class WarehouseSettingsType extends AbstractType
{
    public const array KEYS = [
        SettingKey::WAREHOUSE_NUMBER_PATTERN,
        SettingKey::WAREHOUSE_CREATE_PZ,
    ];

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add(SettingsForm::field(SettingKey::WAREHOUSE_NUMBER_PATTERN), TextType::class, [
                'label' => 'Wzór numeru dokumentu',
                'help' => '{TYP} – PZ, WZ…, {RRRR} – rok, {RR} – rok dwucyfrowo, {MM} – miesiąc, {NNNN} – kolejny numer w miesiącu (liczba N = liczba cyfr). Np. {TYP}/{RRRR}/{MM}/{NNNN} → PZ/2026/10/0001.',
                'constraints' => [
                    new Assert\NotBlank(),
                    new Assert\Length(max: 30),
                    new Assert\Regex(pattern: '/\{N+\}/', message: 'Wzór musi zawierać numer kolejny, np. {NNNN}.'),
                    new Assert\Regex(pattern: '/\{TYP\}/', message: 'Wzór musi zawierać {TYP} – numeracja jest osobna dla każdego typu.'),
                    new Assert\Regex(pattern: '/\{MM\}/', message: 'Wzór musi zawierać {MM} – numeracja jest miesięczna.'),
                ],
            ])
            ->add(SettingsForm::field(SettingKey::WAREHOUSE_CREATE_PZ), CheckboxType::class, [
                'label' => 'Przy przypisaniu towarów domyślnie zaznaczaj „Utwórz też PZ”',
                'required' => false,
            ]);
    }
}
