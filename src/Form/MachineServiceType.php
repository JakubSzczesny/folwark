<?php

declare(strict_types=1);

namespace App\Form;

use App\Entity\Invoice;
use App\Entity\MachineService;
use App\Entity\ServiceType;
use App\Form\Field\PerformerAutocompleteField;
use App\Repository\InvoiceRepository;
use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\QueryBuilder;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\CallbackTransformer;
use Symfony\Component\Form\Extension\Core\Type\CollectionType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Wpis serwisowy. Kwoty i faktury widzi tylko ten, kto ma do nich dostęp.
 *
 * @extends AbstractType<MachineService>
 */
final class MachineServiceType extends AbstractType
{
    public function __construct(
        private readonly InvoiceRepository $invoices,
    ) {
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $date = ['widget' => 'single_text', 'input' => 'datetime_immutable'];
        $meter = ['required' => false, 'scale' => 1, 'input' => 'string', 'html5' => true, 'attr' => ['step' => '0.1']];

        $builder
            ->add('date', DateType::class, ['label' => 'Data', 'attr' => ['data-service-invoice-target' => 'date', 'data-action' => 'change->service-invoice#refresh']] + $date)
            ->add('meterValue', NumberType::class, ['label' => 'Stan licznika'] + $meter)
            ->add('type', EntityType::class, [
                'label' => 'Rodzaj',
                'class' => ServiceType::class,
                'choice_label' => 'name',
                'query_builder' => static fn (EntityRepository $r): QueryBuilder => $r->createQueryBuilder('t')
                    ->where('t.active = true')
                    ->orderBy('t.position')
                    ->addOrderBy('t.name'),
                'placeholder' => 'Wybierz…',
            ])
            ->add('description', TextareaType::class, ['label' => 'Co zrobiono', 'required' => false, 'attr' => ['rows' => 3]])
            ->add('contractor', PerformerAutocompleteField::class, [
                'label' => 'Wykonawca',
                'required' => false,
                'attr' => ['data-service-invoice-target' => 'contractor', 'data-action' => 'change->service-invoice#refresh'],
            ])
            ->add('performerName', TextType::class, ['label' => 'albo wykonawca spoza listy', 'required' => false, 'attr' => ['placeholder' => 'np. we własnym zakresie']])
            ->add('nextServiceDate', DateType::class, ['label' => 'Następny serwis – data', 'required' => false, 'help' => 'Przypomnienie przyjdzie 14 dni wcześniej.'] + $date)
            ->add('nextServiceMeter', NumberType::class, ['label' => 'Następny serwis – przy liczniku'] + $meter)
            ->add('parts', CollectionType::class, [
                'label' => 'Użyte części',
                'entry_type' => ServicePartType::class,
                'entry_options' => ['label' => false],
                'allow_add' => true,
                'allow_delete' => true,
                'by_reference' => false,
                'prototype' => true,
                'required' => false,
            ])
            ->add('newAttachments', FileType::class, [
                'label' => 'Zdjęcia i dokumenty',
                'mapped' => false,
                'required' => false,
                'multiple' => true,
                'help' => 'Np. protokół, zdjęcie licznika lub uszkodzenia. Maks. 20 MB na plik.',
                'constraints' => [new Assert\All([new Assert\File(maxSize: '20M')])],
            ]);

        if ($options['with_amounts']) {
            $builder->add('netCost', NumberType::class, ['label' => 'Koszt netto (zł)', 'required' => false, 'scale' => 2, 'input' => 'string', 'html5' => true, 'attr' => ['step' => '0.01']]);
        }

        if ($options['with_invoice']) {
            $builder->add('invoice', HiddenType::class, ['label' => 'Faktura', 'required' => false]);
            $builder->get('invoice')->addModelTransformer(new CallbackTransformer(
                static fn (?Invoice $invoice): string => (string) $invoice?->getId(),
                fn (?string $id): ?Invoice => null !== $id && ctype_digit($id) ? $this->invoices->find((int) $id) : null,
            ));
        }
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => MachineService::class,
            'with_amounts' => false,
            'with_invoice' => false,
        ]);
        $resolver->setAllowedTypes('with_amounts', 'bool');
        $resolver->setAllowedTypes('with_invoice', 'bool');
    }
}
