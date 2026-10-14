<?php

declare(strict_types=1);

namespace App\Form;

use App\Entity\Contract;
use App\Entity\CostCenter;
use App\Entity\StockDocument;
use App\Entity\Warehouse;
use App\Enum\ContractStatus;
use App\Enum\DocumentType;
use App\Form\Field\ContractorAutocompleteField;
use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\QueryBuilder;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CollectionType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * Nagłówek i pozycje dokumentu; pola zależą od typu dokumentu.
 *
 * @extends AbstractType<StockDocument>
 */
final class StockDocumentType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        /** @var DocumentType $type */
        $type = $options['document_type'];
        $warehouses = [
            'class' => Warehouse::class,
            'choice_label' => 'name',
            'query_builder' => static fn (EntityRepository $r): QueryBuilder => $r->createQueryBuilder('w')->where('w.active = true')->orderBy('w.name'),
            'placeholder' => 'Wybierz…',
            'required' => false,
        ];

        $builder->add('date', DateType::class, ['label' => 'Data', 'widget' => 'single_text', 'input' => 'datetime_immutable']);

        if ($type->needsSource()) {
            $builder->add('sourceWarehouse', EntityType::class, ['label' => DocumentType::Mm === $type ? 'Z magazynu' : 'Magazyn'] + $warehouses);
        }
        if ($type->needsTarget()) {
            $builder->add('targetWarehouse', EntityType::class, ['label' => DocumentType::Mm === $type ? 'Do magazynu' : 'Magazyn'] + $warehouses);
        }
        if ($type->hasContractor()) {
            $builder
                ->add('contractor', ContractorAutocompleteField::class, ['label' => DocumentType::Pz === $type ? 'Dostawca' : 'Odbiorca', 'required' => false])
                ->add('contract', EntityType::class, [
                    'label' => 'Umowa',
                    'class' => Contract::class,
                    'choice_label' => static fn (Contract $c): string => $c->getNumber().' – '.$c->getSubject(),
                    'query_builder' => static fn (EntityRepository $r): QueryBuilder => $r->createQueryBuilder('k')
                        ->where('k.status = :active')
                        ->setParameter('active', ContractStatus::Active)
                        ->orderBy('k.number'),
                    'placeholder' => '—',
                    'required' => false,
                ]);
        }
        if (\in_array($type, [DocumentType::Pz, DocumentType::Rw], true)) {
            $builder->add('costCenter', EntityType::class, [
                'label' => 'Ośrodek kosztów',
                'class' => CostCenter::class,
                'choice_label' => 'name',
                'query_builder' => static fn (EntityRepository $r): QueryBuilder => $r->createQueryBuilder('c')->where('c.active = true')->orderBy('c.type')->addOrderBy('c.name'),
                'placeholder' => '—',
                'required' => false,
            ]);
        }

        $builder
            ->add('notes', TextareaType::class, ['label' => 'Uwagi', 'required' => false, 'attr' => ['rows' => 2]])
            ->add('items', CollectionType::class, [
                'label' => 'Pozycje',
                'entry_type' => StockDocumentItemType::class,
                'entry_options' => ['label' => false, 'with_price' => $type->isPriced() || DocumentType::Inw === $type, 'inventory' => DocumentType::Inw === $type],
                'allow_add' => true,
                'allow_delete' => true,
                'by_reference' => false,
                'prototype' => true,
                'error_bubbling' => false,
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => StockDocument::class]);
        $resolver->setRequired('document_type');
        $resolver->setAllowedTypes('document_type', DocumentType::class);
    }
}
