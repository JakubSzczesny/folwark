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
 * Wykonawca serwisu, ostatnio wybierani na górze. Pracownik nie ma dostępu do kartoteki kontrahentów, więc widzi tylko nazwy.
 *
 * @extends AbstractType<Contractor>
 */
#[AsEntityAutocompleteField(alias: 'performer')]
final class PerformerAutocompleteField extends AbstractType
{
    public const string RECENT_KIND = 'performer';

    public function __construct(
        private readonly RecentChoices $recent,
    ) {
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $recent = $this->recent->ids(self::RECENT_KIND, 8) ?: [0];

        $resolver->setDefaults([
            'class' => Contractor::class,
            'placeholder' => 'Wybierz wykonawcę…',
            'searchable_fields' => ['name', 'shortName'],
            'choice_label' => static fn (Contractor $c): string => $c->getDisplayName(),
            'query_builder' => static fn (EntityRepository $repository): QueryBuilder => $repository->createQueryBuilder('contractor')
                ->addSelect('CASE WHEN contractor.id IN (:recent) THEN 0 ELSE 1 END AS HIDDEN recentRank')
                ->where('contractor.active = true')
                ->setParameter('recent', $recent)
                ->orderBy('recentRank')
                ->addOrderBy('contractor.name'),
            'security' => Permission::MACHINE_EDIT,
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
