<?php

declare(strict_types=1);

namespace App\Entity;

use App\Enum\AttachmentType;
use App\Farm\BelongsToFarm;
use App\Farm\FarmScoped;
use App\Repository\MachineInfoEntryRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Wpis w informacjach o maszynie, np. „Filtr oleju silnika – RE504836, John Deere”.
 */
#[ORM\Entity(repositoryClass: MachineInfoEntryRepository::class)]
#[ORM\HasLifecycleCallbacks]
class MachineInfoEntry implements FarmScoped
{
    use BelongsToFarm;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank(message: 'Podaj nazwę.')]
    #[Assert\Length(max: 255)]
    private string $name = '';

    #[ORM\Column(length: 80, nullable: true)]
    #[Assert\Length(max: 80)]
    private ?string $catalogNumber = null;

    #[ORM\Column(length: 80, nullable: true)]
    #[Assert\Length(max: 80)]
    private ?string $manufacturer = null;

    /** Rozmiar albo ilość, np. „710/75 R42”, „2 szt.”, „18 l”. */
    #[ORM\Column(length: 80, nullable: true)]
    #[Assert\Length(max: 80)]
    private ?string $sizeQuantity = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $notes = null;

    /** @var Collection<int, MachineInfoAttachment> */
    #[ORM\OneToMany(targetEntity: MachineInfoAttachment::class, mappedBy: 'entry', cascade: ['persist', 'remove'], orphanRemoval: true)]
    #[ORM\OrderBy(['id' => 'ASC'])]
    private Collection $attachments;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $updatedAt;

    public function __construct(
        #[ORM\ManyToOne]
        #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
        private Machine $machine,
        #[ORM\ManyToOne]
        #[ORM\JoinColumn(nullable: false)]
        private MachineInfoCategory $category,
    ) {
        $this->attachments = new ArrayCollection();
        $this->createdAt = new \DateTimeImmutable();
        $this->updatedAt = $this->createdAt;
    }

    #[ORM\PreUpdate]
    public function touch(): void
    {
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getMachine(): Machine
    {
        return $this->machine;
    }

    public function getCategory(): MachineInfoCategory
    {
        return $this->category;
    }

    public function setCategory(MachineInfoCategory $category): static
    {
        $this->category = $category;

        return $this;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): static
    {
        $this->name = $name;

        return $this;
    }

    public function getCatalogNumber(): ?string
    {
        return $this->catalogNumber;
    }

    public function setCatalogNumber(?string $catalogNumber): static
    {
        $this->catalogNumber = $catalogNumber;

        return $this;
    }

    public function getManufacturer(): ?string
    {
        return $this->manufacturer;
    }

    public function setManufacturer(?string $manufacturer): static
    {
        $this->manufacturer = $manufacturer;

        return $this;
    }

    public function getSizeQuantity(): ?string
    {
        return $this->sizeQuantity;
    }

    public function setSizeQuantity(?string $sizeQuantity): static
    {
        $this->sizeQuantity = $sizeQuantity;

        return $this;
    }

    public function getNotes(): ?string
    {
        return $this->notes;
    }

    public function setNotes(?string $notes): static
    {
        $this->notes = $notes;

        return $this;
    }

    /**
     * @return Collection<int, MachineInfoAttachment>
     */
    public function getAttachments(): Collection
    {
        return $this->attachments;
    }

    /**
     * @return list<MachineInfoAttachment>
     */
    public function getPhotos(): array
    {
        return $this->attachmentsOfType(AttachmentType::Photo);
    }

    /**
     * @return list<MachineInfoAttachment>
     */
    public function getFiles(): array
    {
        return $this->attachmentsOfType(AttachmentType::File);
    }

    /**
     * @return list<MachineInfoAttachment>
     */
    public function getLinks(): array
    {
        return $this->attachmentsOfType(AttachmentType::Link);
    }

    /**
     * @return list<MachineInfoAttachment>
     */
    private function attachmentsOfType(AttachmentType $type): array
    {
        return array_values($this->attachments->filter(static fn (MachineInfoAttachment $a): bool => $a->getType() === $type)->toArray());
    }

    public function addAttachment(MachineInfoAttachment $attachment): static
    {
        if (!$this->attachments->contains($attachment)) {
            $this->attachments->add($attachment);
        }

        return $this;
    }

    public function removeAttachment(MachineInfoAttachment $attachment): static
    {
        $this->attachments->removeElement($attachment);

        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): \DateTimeImmutable
    {
        return $this->updatedAt;
    }
}
