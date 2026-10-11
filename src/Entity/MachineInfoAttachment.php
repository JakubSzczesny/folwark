<?php

declare(strict_types=1);

namespace App\Entity;

use App\Enum\AttachmentType;
use App\Farm\BelongsToFarm;
use App\Farm\FarmScoped;
use Doctrine\ORM\Mapping as ORM;

/**
 * Zdjęcie, plik (ścieżka w magazynie plików) albo link (adres URL) do wpisu informacji.
 */
#[ORM\Entity]
class MachineInfoAttachment implements FarmScoped
{
    use BelongsToFarm;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    public function __construct(
        #[ORM\ManyToOne(inversedBy: 'attachments')]
        #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
        private MachineInfoEntry $entry,
        #[ORM\Column(length: 10, enumType: AttachmentType::class)]
        private AttachmentType $type,
        #[ORM\Column(length: 1024)]
        private string $location,
        #[ORM\Column(length: 255, nullable: true)]
        private ?string $description = null,
    ) {
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getEntry(): MachineInfoEntry
    {
        return $this->entry;
    }

    public function getType(): AttachmentType
    {
        return $this->type;
    }

    public function isLink(): bool
    {
        return AttachmentType::Link === $this->type;
    }

    public function isPhoto(): bool
    {
        return AttachmentType::Photo === $this->type;
    }

    /**
     * Ścieżka pliku w magazynie albo adres URL linku.
     */
    public function getLocation(): string
    {
        return $this->location;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function getLabel(): string
    {
        if (null !== $this->description && '' !== $this->description) {
            return $this->description;
        }

        return $this->isLink() ? (parse_url($this->location, \PHP_URL_HOST) ?: $this->location) : basename($this->location);
    }
}
