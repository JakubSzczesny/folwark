<?php

declare(strict_types=1);

namespace App\Entity;

use App\Farm\BelongsToFarm;
use App\Farm\FarmScoped;
use App\Repository\ProductCategoryRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: ProductCategoryRepository::class)]
class ProductCategory implements \Stringable, FarmScoped
{
    use BelongsToFarm;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    public function __construct(
        #[ORM\Column(length: 80)]
        #[Assert\NotBlank]
        #[Assert\Length(max: 80)]
        private string $name = '',
        #[ORM\ManyToOne]
        #[ORM\JoinColumn(onDelete: 'CASCADE')]
        private ?ProductCategory $parent = null,
        #[ORM\Column]
        private int $position = 0,
    ) {
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

    public function getParent(): ?ProductCategory
    {
        return $this->parent;
    }

    public function setParent(?ProductCategory $parent): static
    {
        $this->parent = $parent !== $this ? $parent : null;

        return $this;
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

    public function getPath(): string
    {
        return null !== $this->parent ? $this->parent->getPath().' › '.$this->name : $this->name;
    }

    public function __toString(): string
    {
        return $this->getPath();
    }
}
