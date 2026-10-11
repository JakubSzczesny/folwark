<?php

declare(strict_types=1);

namespace App\Form;

use App\Entity\MachineInfoCategory;
use App\Entity\MachineInfoEntry;
use App\Form\Field\ProductAutocompleteField;
use App\Repository\MachineInfoCategoryRepository;
use App\Tree\CategoryTree;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * @extends AbstractType<MachineInfoEntry>
 */
final class MachineInfoEntryType extends AbstractType
{
    public function __construct(
        private readonly MachineInfoCategoryRepository $categories,
    ) {
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('category', EntityType::class, [
                'label' => 'Kategoria',
                'class' => MachineInfoCategory::class,
                'choices' => CategoryTree::ordered($this->categories->findAllOrdered()),
                'choice_label' => static fn (MachineInfoCategory $c): string => $c->getPath(),
            ])
            ->add('name', TextType::class, ['label' => 'Nazwa', 'attr' => ['autofocus' => true, 'placeholder' => 'np. Filtr oleju silnika']])
            ->add('catalogNumber', TextType::class, ['label' => 'Nr katalogowy', 'required' => false])
            ->add('manufacturer', TextType::class, ['label' => 'Producent', 'required' => false])
            ->add('sizeQuantity', TextType::class, ['label' => 'Rozmiar / ilość', 'required' => false, 'attr' => ['placeholder' => 'np. 710/75 R42, 2 szt., 18 l']])
            ->add('notes', TextareaType::class, ['label' => 'Uwagi', 'required' => false, 'attr' => ['rows' => 2]])
            ->add('product', ProductAutocompleteField::class, ['label' => 'Towar z kartoteki', 'required' => false, 'help' => 'Wtedy przy wpisie widać ostatni zakup, termin zamówienia i linki do sklepów.'])
            ->add('files', FileType::class, [
                'label' => 'Zdjęcia i pliki',
                'mapped' => false,
                'required' => false,
                'multiple' => true,
                'help' => 'Na telefonie można od razu zrobić zdjęcie. Maks. 20 MB na plik.',
                'constraints' => [new Assert\All([new Assert\File(maxSize: '20M')])],
            ])
            ->add('links', TextareaType::class, [
                'label' => 'Linki',
                'mapped' => false,
                'required' => false,
                'attr' => ['rows' => 2, 'placeholder' => "Sklep Agrihandel https://…\nhttps://…"],
                'help' => 'Każdy link w osobnej linii; tekst przed adresem będzie jego opisem.',
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => MachineInfoEntry::class]);
    }
}
