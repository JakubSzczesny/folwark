<?php

declare(strict_types=1);

namespace App\Form\Field;

use App\Entity\Product;
use App\Security\Permission;
use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\QueryBuilder;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\UX\Autocomplete\Form\AsEntityAutocompleteField;
use Symfony\UX\Autocomplete\Form\BaseEntityAutocompleteType;

/**
 * Combobox towaru z wyszukiwaniem po nazwie, kodzie i numerze katalogowym.
 *
 * @extends AbstractType<Product>
 */
#[AsEntityAutocompleteField(alias: 'product')]
final class ProductAutocompleteField extends AbstractType
{
    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'class' => Product::class,
            'placeholder' => 'Wybierz towar…',
            'searchable_fields' => ['name', 'internalCode', 'catalogNumber'],
            'choice_label' => static fn (Product $p): string => $p->getName().('' !== $p->getUnitSymbol() ? ' ['.$p->getUnitSymbol().']' : ''),
            'query_builder' => static fn (EntityRepository $repository): QueryBuilder => $repository->createQueryBuilder('product')
                ->where('product.active = true')
                ->orderBy('product.name'),
            'security' => Permission::PRODUCT_VIEW,
            'preload' => 'focus',
            'max_results' => 30,
            'no_results_found_text' => 'Brak takiego towaru',
            'no_more_results_text' => 'Brak kolejnych wyników',
        ]);
    }

    public function getParent(): string
    {
        return BaseEntityAutocompleteType::class;
    }
}
