<?php

declare(strict_types=1);

namespace App\Form\Settings;

use App\Form\DataTransformer\IntListTransformer;
use App\Settings\SettingKey;
use App\Settings\SettingsForm;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * @extends AbstractType<array<string, mixed>>
 */
final class NotificationSettingsType extends AbstractType
{
    public const array KEYS = [
        SettingKey::NOTIFY_PAYMENT_DAYS,
        SettingKey::NOTIFY_INSURANCE_DAYS,
        SettingKey::NOTIFY_INSPECTION_DAYS,
        SettingKey::NOTIFY_SERVICE_DAYS,
        SettingKey::NOTIFY_CONTRACT_DAYS,
        SettingKey::NOTIFY_KSEF_REPEAT_HOURS,
        SettingKey::ATTENTION_DAYS,
        SettingKey::NOTIFY_DIGEST_EMAIL,
    ];

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $days = [new Assert\NotBlank(), new Assert\Range(min: 0, max: 365)];

        $builder
            ->add(SettingsForm::field(SettingKey::NOTIFY_PAYMENT_DAYS), IntegerType::class, [
                'label' => 'Termin płatności – ile dni przed',
                'help' => 'Powiadomienie przychodzi też w dniu terminu i po terminie.',
                'constraints' => $days,
            ])
            ->add(SettingsForm::field(SettingKey::NOTIFY_INSURANCE_DAYS), TextType::class, [
                'label' => 'Ubezpieczenie maszyny – dni przed',
                'help' => 'Kilka wartości oddziel przecinkami, np. 30, 14, 3.',
            ])
            ->add(SettingsForm::field(SettingKey::NOTIFY_INSPECTION_DAYS), TextType::class, [
                'label' => 'Przegląd techniczny – dni przed',
            ])
            ->add(SettingsForm::field(SettingKey::NOTIFY_SERVICE_DAYS), IntegerType::class, [
                'label' => 'Zaplanowany serwis – dni przed',
                'constraints' => $days,
            ])
            ->add(SettingsForm::field(SettingKey::NOTIFY_CONTRACT_DAYS), IntegerType::class, [
                'label' => 'Umowa wygasa – dni przed',
                'constraints' => $days,
            ])
            ->add(SettingsForm::field(SettingKey::NOTIFY_KSEF_REPEAT_HOURS), IntegerType::class, [
                'label' => 'Błąd KSeF – przypomnij po (godz.)',
                'constraints' => [new Assert\NotBlank(), new Assert\Range(min: 1, max: 168)],
            ])
            ->add(SettingsForm::field(SettingKey::ATTENTION_DAYS), IntegerType::class, [
                'label' => 'Pulpit „Wymaga uwagi” – horyzont terminów (dni)',
                'help' => 'Ubezpieczenia, przeglądy, serwisy i umowy w tym okresie pojawiają się na pulpicie.',
                'constraints' => $days,
            ])
            ->add(SettingsForm::field(SettingKey::NOTIFY_DIGEST_EMAIL), CheckboxType::class, [
                'label' => 'Wysyłaj dzienny skrót e-mail o 6:00',
                'required' => false,
            ]);

        $transformer = new IntListTransformer();
        $builder->get(SettingsForm::field(SettingKey::NOTIFY_INSURANCE_DAYS))->addModelTransformer($transformer);
        $builder->get(SettingsForm::field(SettingKey::NOTIFY_INSPECTION_DAYS))->addModelTransformer($transformer);
    }
}
