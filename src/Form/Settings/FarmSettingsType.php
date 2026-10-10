<?php

declare(strict_types=1);

namespace App\Form\Settings;

use App\Settings\SettingKey;
use App\Settings\SettingsForm;
use App\Validator\ValidNip;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * @extends AbstractType<array<string, mixed>>
 */
final class FarmSettingsType extends AbstractType
{
    public const array KEYS = [SettingKey::FARM_NAME, SettingKey::FARM_NIP];

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add(SettingsForm::field(SettingKey::FARM_NAME), TextType::class, [
                'label' => 'Nazwa gospodarstwa',
                'constraints' => [new Assert\NotBlank()],
            ])
            ->add(SettingsForm::field(SettingKey::FARM_NIP), TextType::class, [
                'label' => 'NIP gospodarstwa',
                'required' => false,
                'help' => 'Na tej podstawie faktury dzielą się na kosztowe (gospodarstwo jest nabywcą) i przychodowe.',
                'constraints' => [new ValidNip()],
            ]);
    }
}
