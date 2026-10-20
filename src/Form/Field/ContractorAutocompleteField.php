<?php

declare(strict_types=1);

namespace App\Form\Field;

use App\Entity\Contractor;
use App\Security\Permission;
use App\Ui\RecentChoices;
use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\QueryBuilder;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\UX\Autocomplete\Form\AsEntityAutocompleteField;
use Symfony\UX\Autocomplete\Form\BaseEntityAutocompleteType;

/**
 * Combobox kontrahenta z wyszukiwaniem po nazwie i NIP; ostatnio używani na górze.
 *
 * @extends AbstractType<Contractor>
 */
#[AsEntityAutocompleteField(alias: 'contractor')]
final class ContractorAutocompleteField extends AbstractType
{
    public const string RECENT_KIND = 'contractor';

    public function __construct(
        private readonly RecentChoices $recent,
    ) {
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $recent = $this->recent->ids(self::RECENT_KIND, 8) ?: [0];

        $resolver->setDefaults([
            'class' => Contractor::class,
            'placeholder' => 'Wybierz kontrahenta…',
            'searchable_fields' => ['name', 'shortName', 'nip'],
            'choice_label' => static fn (Contractor $c): string => $c->getName().(null !== $c->getNip() ? ' · NIP '.$c->getNip() : ''),
            'query_builder' => static fn (EntityRepository $repository): QueryBuilder => $repository->createQueryBuilder('contractor')
                ->addSelect('CASE WHEN contractor.id IN (:recent) THEN 0 ELSE 1 END AS HIDDEN recentRank')
                ->where('contractor.active = true')
                ->setParameter('recent', $recent)
                ->orderBy('recentRank')
                ->addOrderBy('contractor.name'),
            'security' => Permission::CONTRACTOR_LOOKUP,
            'preload' => 'focus',
            'max_results' => 20,
            'no_results_found_text' => 'Brak wyników',
            'no_more_results_text' => 'Brak kolejnych wyników',
        ]);
    }

    public function getParent(): string
    {
        return BaseEntityAutocompleteType::class;
    }
}
