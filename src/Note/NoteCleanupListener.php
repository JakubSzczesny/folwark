<?php

declare(strict_types=1);

namespace App\Note;

use App\Repository\NoteRepository;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsDoctrineListener;
use Doctrine\ORM\Event\OnFlushEventArgs;
use Doctrine\ORM\Event\PostFlushEventArgs;
use Doctrine\ORM\Events;

/**
 * Usunięcie kontrahenta, maszyny albo faktury usuwa też ich notatki (powiązanie nie ma klucza obcego).
 */
#[AsDoctrineListener(event: Events::onFlush)]
#[AsDoctrineListener(event: Events::postFlush)]
final class NoteCleanupListener
{
    /** @var array<string, list<int>> rodzaj => identyfikatory usuwanych obiektów */
    private array $removed = [];

    public function __construct(
        private readonly NoteRepository $notes,
    ) {
    }

    public function onFlush(OnFlushEventArgs $args): void
    {
        foreach ($args->getObjectManager()->getUnitOfWork()->getScheduledEntityDeletions() as $entity) {
            $type = NoteSubjects::typeOf($entity);
            $id = $type && method_exists($entity, 'getId') ? $entity->getId() : null;
            if (null !== $type && \is_int($id)) {
                $this->removed[$type][] = $id;
            }
        }
    }

    public function postFlush(PostFlushEventArgs $args): void
    {
        $removed = $this->removed;
        $this->removed = [];
        foreach ($removed as $type => $ids) {
            $this->notes->deleteFor($type, $ids);
        }
    }
}
