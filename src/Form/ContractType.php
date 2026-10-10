<?php

declare(strict_types=1);

namespace App\Form;

use App\Entity\Contract;
use App\Entity\Unit;
use App\Enum\ContractKind;
use App\Enum\ContractStatus;
use App\Enum\PaymentFrequency;
use App\Form\Field\ContractorAutocompleteField;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\EnumType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * @extends AbstractType<Contract>
 */
final class ContractType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $date = ['widget' => 'single_text', 'input' => 'datetime_immutable', 'required' => false];
        $unit = ['class' => Unit::class, 'choice_label' => 'symbol', 'required' => false, 'placeholder' => '—'];

        $builder
            ->add('contractor', ContractorAutocompleteField::class, ['label' => 'Kontrahent'])
            ->add('number', TextType::class, ['label' => 'Numer umowy'])
            ->add('subject', TextType::class, ['label' => 'Przedmiot', 'help' => 'Np. „Sprzedaż pszenicy ozimej ze zbiorów 2026”.'])
            ->add('kind', EnumType::class, [
                'label' => 'Rodzaj',
                'class' => ContractKind::class,
                'choice_label' => static fn (ContractKind $k): string => $k->label(),
                'attr' => ['data-contract-form-target' => 'kind', 'data-action' => 'contract-form#toggle'],
            ])
            ->add('concludedAt', DateType::class, ['label' => 'Data zawarcia'] + $date)
            ->add('validFrom', DateType::class, ['label' => 'Obowiązuje od'] + $date)
            ->add('validTo', DateType::class, ['label' => 'Obowiązuje do', 'help' => 'Puste = umowa bezterminowa.'] + $date)
            ->add('contractedQuantity', NumberType::class, ['label' => 'Ilość kontraktowa', 'required' => false, 'scale' => 3, 'input' => 'string', 'html5' => true, 'attr' => ['step' => 'any']])
            ->add('quantityUnit', EntityType::class, ['label' => 'Jednostka ilości'] + $unit)
            ->add('price', NumberType::class, ['label' => 'Cena netto', 'required' => false, 'scale' => 4, 'input' => 'string', 'html5' => true, 'attr' => ['step' => 'any']])
            ->add('priceUnit', EntityType::class, ['label' => 'Cena za jednostkę'] + $unit)
            ->add('rentAmount', NumberType::class, ['label' => 'Kwota płatności (zł)', 'required' => false, 'scale' => 2, 'input' => 'string', 'html5' => true, 'attr' => ['step' => '0.01'], 'help' => 'Jedna rata czynszu.'])
            ->add('paymentFrequency', EnumType::class, [
                'label' => 'Płatna',
                'class' => PaymentFrequency::class,
                'choice_label' => static fn (PaymentFrequency $f): string => $f->label(),
                'required' => false,
                'placeholder' => '—',
            ])
            ->add('firstPaymentDate', DateType::class, ['label' => 'Pierwsza płatność', 'help' => 'Od tej daty liczą się kolejne terminy.'] + $date)
            ->add('qualityTerms', TextareaType::class, ['label' => 'Warunki jakościowe', 'required' => false])
            ->add('status', EnumType::class, [
                'label' => 'Status',
                'class' => ContractStatus::class,
                'choice_label' => static fn (ContractStatus $s): string => $s->label(),
                'expanded' => true,
            ])
            ->add('notes', TextareaType::class, ['label' => 'Uwagi', 'required' => false])
            ->add('newAttachments', FileType::class, [
                'label' => 'Dodaj załączniki',
                'mapped' => false,
                'required' => false,
                'multiple' => true,
                'help' => 'Skan umowy, aneksy (PDF, zdjęcia, dokumenty). Maks. 20 MB na plik.',
                'constraints' => [new Assert\All([new Assert\File(maxSize: '20M')])],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => Contract::class]);
    }
}
