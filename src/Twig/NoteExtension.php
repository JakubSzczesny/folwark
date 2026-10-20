<?php

declare(strict_types=1);

namespace App\Twig;

use App\Entity\Note;
use App\Note\NoteSubjects;
use App\Repository\NoteRepository;
use Twig\Attribute\AsTwigFunction;

final class NoteExtension
{
    public function __construct(
        private readonly NoteRepository $notes,
    ) {
    }

    /**
     * @return list<Note> od najnowszej
     */
    #[AsTwigFunction('notes_for')]
    public function notesFor(object $subject): array
    {
        [$type, $id] = self::key($subject);

        return $this->notes->findFor($type, $id);
    }

    #[AsTwigFunction('notes_count')]
    public function notesCount(object $subject): int
    {
        [$type, $id] = self::key($subject);

        return $this->notes->countFor($type, $id);
    }

    #[AsTwigFunction('notes_type')]
    public function notesType(object $subject): string
    {
        return self::key($subject)[0];
    }

    /**
     * @return array{string, int}
     */
    private static function key(object $subject): array
    {
        $type = NoteSubjects::typeOf($subject) ?? throw new \InvalidArgumentException(\sprintf('Do „%s” nie dodaje się notatek.', $subject::class));
        $id = method_exists($subject, 'getId') ? $subject->getId() : null;

        return [$type, \is_int($id) ? $id : 0];
    }
}
