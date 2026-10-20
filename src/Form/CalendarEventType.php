<?php

declare(strict_types=1);

namespace App\Form;

use App\Entity\CalendarEvent;
use App\Entity\User;
use App\Enum\CalendarCategory;
use App\Enum\CalendarRecurrence;
use App\Form\Field\ContractorAutocompleteField;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\EnumType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\TimeType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * @extends AbstractType<CalendarEvent>
 */
final class CalendarEventType extends AbstractType
{
    public const array REMINDERS = [
        'Bez przypomnienia' => null,
        'W dniu wydarzenia' => 0,
        'Dzień wcześniej' => 1,
        '2 dni wcześniej' => 2,
        'Tydzień wcześniej' => 7,
        '2 tygodnie wcześniej' => 14,
        'Miesiąc wcześniej' => 30,
    ];

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $date = ['widget' => 'single_text', 'input' => 'datetime_immutable'];
        $time = ['widget' => 'single_text', 'input' => 'datetime_immutable', 'required' => false];

        $builder
            ->add('title', TextType::class, ['label' => 'Tytuł', 'attr' => ['maxlength' => 160, 'placeholder' => 'np. Siew rzepaku – pole za stodołą', 'autofocus' => true]])
            ->add('category', EnumType::class, [
                'label' => 'Kategoria',
                'class' => CalendarCategory::class,
                'choice_label' => static fn (CalendarCategory $c): string => $c->label(),
            ])
            ->add('startDate', DateType::class, ['label' => 'Data'] + $date)
            ->add('startTime', TimeType::class, ['label' => 'Od godziny', 'help' => 'Puste = cały dzień.'] + $time)
            ->add('endDate', DateType::class, ['label' => 'Do dnia', 'required' => false, 'help' => 'Dla wydarzeń kilkudniowych.'] + $date)
            ->add('endTime', TimeType::class, ['label' => 'Do godziny'] + $time)
            ->add('location', TextType::class, ['label' => 'Miejsce', 'required' => false, 'attr' => ['maxlength' => 160]])
            ->add('contractor', ContractorAutocompleteField::class, ['label' => 'Kontrahent', 'required' => false, 'help' => 'Wydarzenie pokaże się też na karcie kontrahenta.'])
            ->add('description', TextareaType::class, ['label' => 'Opis', 'required' => false, 'attr' => ['rows' => 3]])
            ->add('recurrence', EnumType::class, [
                'label' => 'Powtarzanie',
                'class' => CalendarRecurrence::class,
                'choice_label' => static fn (CalendarRecurrence $r): string => $r->label(),
            ])
            ->add('recurrenceUntil', DateType::class, ['label' => 'Powtarzaj do', 'required' => false, 'help' => 'Puste = bez końca.'] + $date)
            ->add('reminderDays', ChoiceType::class, [
                'label' => 'Przypomnienie',
                'choices' => self::REMINDERS,
                'choice_value' => static fn (?int $days): string => null === $days ? '' : (string) $days,
                'required' => false,
                'placeholder' => false,
                'help' => 'Powiadomienie przychodzi rano (o 6:00) do uczestników, a bez uczestników – do wszystkich.',
            ])
            ->add('participants', EntityType::class, [
                'label' => 'Uczestnicy',
                'class' => User::class,
                'choices' => $options['members'],
                'choice_label' => static fn (User $u): string => $u->getFullName(),
                'multiple' => true,
                'expanded' => true,
                'required' => false,
                'by_reference' => false,
            ])
            ->add('private', CheckboxType::class, [
                'label' => 'Tylko dla mnie',
                'required' => false,
                'help' => 'Prywatne wydarzenie widzisz tylko Ty.',
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => CalendarEvent::class, 'members' => []]);
        $resolver->setAllowedTypes('members', 'array');
    }
}
