<?php

declare(strict_types=1);

namespace App\Farm;

use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\ORM\Query\Filter\SQLFilter;

/**
 * Zawęża każde zapytanie o encje FarmScoped do bieżącego gospodarstwa.
 */
final class FarmFilter extends SQLFilter
{
    public const string NAME = 'farm';

    public function addFilterConstraint(ClassMetadata $targetEntity, string $targetTableAlias): string
    {
        if (!is_subclass_of($targetEntity->getName(), FarmScoped::class)) {
            return '';
        }

        return \sprintf('%s.farm_id = %s', $targetTableAlias, $this->getParameter('farm'));
    }
}
