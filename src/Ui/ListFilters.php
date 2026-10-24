<?php

declare(strict_types=1);

namespace App\Ui;

use App\Entity\SavedFilter;
use App\Entity\User;
use App\Repository\SavedFilterRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;

/**
 * Filtry list żyją w adresie URL; ostatnio użyty zestaw zapamiętujemy per użytkownik.
 * Wejście na listę bez parametrów przywraca zapisane filtry, "?wyczysc=1" je kasuje.
 * Parametry z $notRemembered działają tylko w bieżącym adresie i nie trafiają do zapisanego zestawu.
 */
final class ListFilters
{
    private const array IGNORED = ['strona', 'eksport', 'podglad'];

    public function __construct(
        private readonly SavedFilterRepository $repository,
        private readonly EntityManagerInterface $em,
        private readonly Security $security,
    ) {
    }

    /**
     * @param list<string> $notRemembered
     */
    public function restore(Request $request, string $listKey, array $notRemembered = []): ?RedirectResponse
    {
        $user = $this->security->getUser();
        if (!$user instanceof User || !$request->isMethod('GET')) {
            return null;
        }

        $saved = $this->repository->findOneBy(['user' => $user, 'listKey' => $listKey]);

        if ($request->query->has('wyczysc')) {
            if (null !== $saved) {
                $this->em->remove($saved);
                $this->em->flush();
            }

            return new RedirectResponse($request->getPathInfo());
        }

        $params = array_diff_key($request->query->all(), array_flip(self::IGNORED));
        if ([] === $params) {
            parse_str($saved?->getQuery() ?? '', $savedParams);
            // Zestawy zapisane, zanim parametr przestał być zapamiętywany, też go gubią.
            $savedQuery = http_build_query(array_diff_key($savedParams, array_flip($notRemembered)));
            if ('' !== $savedQuery) {
                return new RedirectResponse($request->getPathInfo().'?'.$savedQuery);
            }

            return null;
        }

        $query = http_build_query(array_diff_key($params, array_flip($notRemembered)));
        $saved ??= new SavedFilter($user, $listKey);
        if ($saved->getQuery() !== $query) {
            $saved->setQuery($query);
            $this->em->persist($saved);
            $this->em->flush();
        }

        return null;
    }
}
