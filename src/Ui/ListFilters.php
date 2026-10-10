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

    public function restore(Request $request, string $listKey): ?RedirectResponse
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
            if (null !== $saved && '' !== $saved->getQuery()) {
                return new RedirectResponse($request->getPathInfo().'?'.$saved->getQuery());
            }

            return null;
        }

        $query = http_build_query($params);
        $saved ??= new SavedFilter($user, $listKey);
        if ($saved->getQuery() !== $query) {
            $saved->setQuery($query);
            $this->em->persist($saved);
            $this->em->flush();
        }

        return null;
    }
}
