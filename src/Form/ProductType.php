<?php

declare(strict_types=1);

namespace App\Form;

use App\Entity\Product;
use App\Entity\ProductCategory;
use App\Entity\Unit;
use App\Repository\ProductCategoryRepository;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\CollectionType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * @extends AbstractType<Product>
 */
final class ProductType extends AbstractType
{
    public function __construct(
        private readonly ProductCategoryRepository $categories,
    ) {
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('name', TextType::class, ['label' => 'Nazwa', 'attr' => ['autofocus' => true, 'placeholder' => 'np. Olej napędowy, Mieszanka DJ 18%, Polifoska 6']])
            ->add('category', EntityType::class, [
                'label' => 'Kategoria',
                'class' => ProductCategory::class,
                'choices' => $this->categories->findTreeOrdered(),
                'choice_label' => static fn (ProductCategory $c): string => $c->getPath(),
                'required' => false,
                'placeholder' => '—',
            ])
            ->add('baseUnit', EntityType::class, [
                'label' => 'Jednostka',
                'class' => Unit::class,
                'choice_label' => static fn (Unit $u): string => $u->getSymbol().' – '.$u->getName(),
                'placeholder' => 'Wybierz…',
                'help' => 'W niej liczymy ilości, ceny i zużycie.',
            ]);

        if ($options['compact']) {
            return;
        }

        $builder
            ->add('internalCode', TextType::class, ['label' => 'Kod wewnętrzny', 'required' => false])
            ->add('catalogNumber', TextType::class, ['label' => 'Nr katalogowy', 'required' => false, 'help' => 'Pomaga rozpoznać towar na fakturze.'])
            ->add('leadTimeDays', IntegerType::class, ['label' => 'Czas dostawy (dni)', 'required' => false, 'help' => 'Przypomnienie o zamówieniu przyjdzie o tyle wcześniej.'])
            ->add('conversions', CollectionType::class, [
                'label' => 'Przeliczniki jednostek',
                'entry_type' => ProductUnitConversionType::class,
                'entry_options' => ['label' => false],
                'allow_add' => true,
                'allow_delete' => true,
                'by_reference' => false,
                'required' => false,
            ])
            ->add('notes', TextareaType::class, ['label' => 'Uwagi', 'required' => false, 'attr' => ['rows' => 3]])
            ->add('active', CheckboxType::class, ['label' => 'Towar w użyciu', 'required' => false, 'help' => 'Nieaktywnego nie podpowiadamy przy fakturach i nie przewidujemy zamówień.']);

        if ($options['with_amounts']) {
            $builder->add('bookPrice', NumberType::class, ['label' => 'Cena ewidencyjna netto (zł)', 'required' => false, 'scale' => 4, 'input' => 'string', 'html5' => true, 'attr' => ['step' => 'any']]);
        }
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Product::class,
            'compact' => false,
            'with_amounts' => false,
        ]);
        $resolver->setAllowedTypes('compact', 'bool');
        $resolver->setAllowedTypes('with_amounts', 'bool');
    }
}
