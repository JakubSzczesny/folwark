<?php

declare(strict_types=1);

namespace App\Entity;

use App\Farm\BelongsToFarm;
use App\Farm\FarmScoped;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
class MachineServiceAttachment implements FarmScoped
{
    use BelongsToFarm;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    public function __construct(
        #[ORM\ManyToOne(inversedBy: 'attachments')]
        #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
        private MachineService $service,
        #[ORM\Column(length: 255)]
        private string $path,
        #[ORM\Column(length: 255)]
        private string $name,
        #[ORM\Column]
        private bool $photo = false,
    ) {
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getService(): MachineService
    {
        return $this->service;
    }

    public function getPath(): string
    {
        return $this->path;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function isPhoto(): bool
    {
        return $this->photo;
    }
}
