<?php

declare(strict_types=1);

namespace App\Entity;

use App\Farm\BelongsToFarm;
use App\Farm\FarmScoped;
use App\Repository\MachineInfoCategoryRepository;
use App\Tree\TreeCategory;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Kategoria informacji o maszynach (filtry, oleje, opony…). Drzewo jest wspólne dla wszystkich maszyn gospodarstwa.
 */
#[ORM\Entity(repositoryClass: MachineInfoCategoryRepository::class)]
class MachineInfoCategory implements \Stringable, FarmScoped, TreeCategory
{
    use BelongsToFarm;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    /** @var Collection<int, MachineInfoCategory> */
    #[ORM\OneToMany(targetEntity: self::class, mappedBy: 'parent')]
    #[ORM\OrderBy(['position' => 'ASC', 'id' => 'ASC'])]
    private Collection $children;

    public function __construct(
        #[ORM\Column(length: 80)]
        #[Assert\NotBlank(message: 'Podaj nazwę kategorii.')]
        #[Assert\Length(max: 80)]
        private string $name = '',
        #[ORM\ManyToOne(inversedBy: 'children')]
        #[ORM\JoinColumn(onDelete: 'CASCADE')]
        private ?MachineInfoCategory $parent = null,
        #[ORM\Column]
        private int $position = 0,
    ) {
        $this->children = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
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

    public function getParent(): ?MachineInfoCategory
    {
        return $this->parent;
    }

    public function getPosition(): int
    {
        return $this->position;
    }

    public function setPosition(int $position): static
    {
        $this->position = $position;

        return $this;
    }

    /**
     * @return Collection<int, MachineInfoCategory>
     */
    public function getChildren(): Collection
    {
        return $this->children;
    }

    public function getPath(): string
    {
        return null !== $this->parent ? $this->parent->getPath().' › '.$this->name : $this->name;
    }

    public function __toString(): string
    {
        return $this->getPath();
    }
}
