<?php

declare(strict_types=1);

namespace App\Entity;

use App\Farm\BelongsToFarm;
use App\Farm\FarmScoped;
use App\Repository\NoteRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Notatka (wpis jak komentarz) przy kontrahencie, maszynie albo fakturze – App\Note\NoteSubjects.
 */
#[ORM\Entity(repositoryClass: NoteRepository::class)]
#[ORM\Index(columns: ['farm_id', 'subject_type', 'subject_id', 'created_at'])]
class Note implements FarmScoped
{
    use BelongsToFarm;

    public const int MAX_LENGTH = 5000;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(type: Types::TEXT)]
    #[Assert\NotBlank(message: 'Wpisz treść notatki.')]
    #[Assert\Length(max: self::MAX_LENGTH)]
    private string $body;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(onDelete: 'SET NULL')]
    private ?User $author;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $editedAt = null;

    public function __construct(
        #[ORM\Column(length: 20)]
        private string $subjectType,
        #[ORM\Column]
        private int $subjectId,
        string $body,
        ?User $author,
    ) {
        $this->body = trim($body);
        $this->author = $author;
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getSubjectType(): string
    {
        return $this->subjectType;
    }

    public function getSubjectId(): int
    {
        return $this->subjectId;
    }

    public function getBody(): string
    {
        return $this->body;
    }

    public function edit(string $body): static
    {
        $body = trim($body);
        if ($body !== $this->body) {
            $this->body = $body;
            $this->editedAt = new \DateTimeImmutable();
        }

        return $this;
    }

    public function getAuthor(): ?User
    {
        return $this->author;
    }

    public function isAuthoredBy(?User $user): bool
    {
        return null !== $user && null !== $this->author && $this->author->getId() === $user->getId();
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getEditedAt(): ?\DateTimeImmutable
    {
        return $this->editedAt;
    }
}
