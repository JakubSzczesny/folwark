<?php

declare(strict_types=1);

namespace App\Form;

use App\Entity\Machine;
use App\Entity\MachineCategory;
use App\Enum\MeterUnit;
use App\Form\Field\ContractorAutocompleteField;
use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\QueryBuilder;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\EnumType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * @extends AbstractType<Machine>
 */
final class MachineType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $machine = $builder->getData();
        $date = ['widget' => 'single_text', 'input' => 'datetime_immutable', 'required' => false];

        $builder
            ->add('name', TextType::class, ['label' => 'Nazwa', 'help' => 'Tak, jak mówicie o niej na co dzień, np. „John Deere 6155M”.', 'attr' => ['autofocus' => true]])
            ->add('category', EntityType::class, [
                'label' => 'Kategoria',
                'class' => MachineCategory::class,
                'choice_label' => 'name',
                'query_builder' => static fn (EntityRepository $r): QueryBuilder => $r->createQueryBuilder('c')->orderBy('c.position')->addOrderBy('c.name'),
                'required' => false,
                'placeholder' => '—',
            ])
            ->add('registrationNumber', TextType::class, ['label' => 'Nr rejestracyjny', 'required' => false])
            ->add('brand', TextType::class, ['label' => 'Marka', 'required' => false])
            ->add('model', TextType::class, ['label' => 'Model', 'required' => false])
            ->add('productionYear', IntegerType::class, ['label' => 'Rok produkcji', 'required' => false])
            ->add('serialNumber', TextType::class, ['label' => 'Nr seryjny / VIN', 'required' => false])
            ->add('owned', CheckboxType::class, [
                'label' => 'Maszyna własna',
                'required' => false,
                'help' => 'Odznacz dla pojazdów obcych, np. przewoźnika przyjeżdżającego na wagę.',
                'attr' => ['data-action' => 'toggle#toggle'],
            ])
            ->add('contractor', ContractorAutocompleteField::class, ['label' => 'Właściciel pojazdu', 'required' => false])
            ->add('tareKg', IntegerType::class, [
                'label' => 'Tara (kg)',
                'mapped' => false,
                'required' => false,
                'data' => $machine instanceof Machine ? $machine->getTareKg() : null,
                'constraints' => [new Assert\PositiveOrZero()],
                'help' => 'Zmiana trafia do historii tary.',
            ])
            ->add('payloadKg', IntegerType::class, ['label' => 'Ładowność (kg)', 'required' => false])
            ->add('insuranceUntil', DateType::class, ['label' => 'Ubezpieczenie do'] + $date)
            ->add('inspectionUntil', DateType::class, ['label' => 'Przegląd techniczny do'] + $date)
            ->add('meterValue', NumberType::class, ['label' => 'Stan licznika', 'required' => false, 'scale' => 1, 'input' => 'string', 'html5' => true, 'attr' => ['step' => '0.1']])
            ->add('meterUnit', EnumType::class, [
                'label' => 'Licznik w',
                'class' => MeterUnit::class,
                'choice_label' => static fn (MeterUnit $u): string => $u->longLabel(),
                'expanded' => true,
                'required' => false,
                'placeholder' => 'brak licznika',
            ])
            ->add('components', EntityType::class, [
                'label' => 'Składniki zestawu',
                'class' => Machine::class,
                'choice_label' => 'label',
                'multiple' => true,
                'required' => false,
                'autocomplete' => true,
                'query_builder' => static fn (EntityRepository $r): QueryBuilder => $r->createQueryBuilder('m')
                    ->where('m.active = true')
                    ->andWhere('m != :self')
                    ->setParameter('self', $machine instanceof Machine && null !== $machine->getId() ? $machine->getId() : 0)
                    ->orderBy('m.name'),
                'help' => 'Dla zestawów, np. ciągnik + przyczepa.',
            ])
            ->add('photo', FileType::class, [
                'label' => 'Zdjęcie',
                'mapped' => false,
                'required' => false,
                'attr' => ['accept' => 'image/*'],
                'constraints' => [new Assert\Image(maxSize: '20M', mimeTypesMessage: 'Wybierz zdjęcie (JPG, PNG albo WebP).')],
            ])
            ->add('active', CheckboxType::class, ['label' => 'Maszyna w użyciu', 'required' => false, 'help' => 'Wycofane nie pokazują się na liście i nie generują przypomnień.']);

        if ($machine instanceof Machine && null !== $machine->getPhotoPath()) {
            $builder->add('removePhoto', CheckboxType::class, ['label' => 'Usuń obecne zdjęcie', 'mapped' => false, 'required' => false]);
        }
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => Machine::class]);
    }
}
