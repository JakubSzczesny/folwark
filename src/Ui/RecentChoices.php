<?php

declare(strict_types=1);

namespace App\Ui;

use App\Entity\RecentChoice;
use App\Entity\User;
use App\Repository\RecentChoiceRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;

/**
 * Pamięta, co użytkownik ostatnio wybierał w comboboxach, żeby pokazać to na górze listy.
 */
final class RecentChoices
{
    public function __construct(
        private readonly RecentChoiceRepository $repository,
        private readonly EntityManagerInterface $em,
        private readonly Security $security,
    ) {
    }

    public function remember(string $kind, ?int $entityId): void
    {
        $user = $this->security->getUser();
        if (null === $entityId || !$user instanceof User) {
            return;
        }

        $choice = $this->repository->findOneBy(['user' => $user, 'kind' => $kind, 'entityId' => $entityId]);
        if (null === $choice) {
            $this->em->persist(new RecentChoice($user, $kind, $entityId));
        } else {
            $choice->touch();
        }
    }

    /**
     * @return list<int>
     */
    public function ids(string $kind, int $limit = 5): array
    {
        $user = $this->security->getUser();
        if (!$user instanceof User) {
            return [];
        }

        return $this->repository->findRecentIds($user, $kind, $limit);
    }

    /**
     * Sortuje encje tak, by ostatnio używane były na początku, reszta w dotychczasowej kolejności.
     *
     * @template T of object
     *
     * @param iterable<T> $entities
     *
     * @return list<T>
     */
    public function sort(string $kind, iterable $entities, int $limit = 5): array
    {
        $recent = array_flip($this->ids($kind, $limit));
        $top = [];
        $rest = [];
        foreach ($entities as $entity) {
            $id = method_exists($entity, 'getId') ? $entity->getId() : null;
            if (null !== $id && isset($recent[$id])) {
                $top[$recent[$id]] = $entity;
            } else {
                $rest[] = $entity;
            }
        }
        ksort($top);

        return [...array_values($top), ...$rest];
    }
}
