<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\ContractAttachmentRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ContractAttachmentRepository::class)]
class ContractAttachment
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $addedAt;

    public function __construct(
        #[ORM\ManyToOne(inversedBy: 'attachments')]
        #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
        private Contract $contract,
        #[ORM\Column(length: 255)]
        private string $path,
        #[ORM\Column(length: 255)]
        private string $name,
    ) {
        $this->addedAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getContract(): Contract
    {
        return $this->contract;
    }

    public function getPath(): string
    {
        return $this->path;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getAddedAt(): \DateTimeImmutable
    {
        return $this->addedAt;
    }
}
