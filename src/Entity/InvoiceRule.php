<?php

declare(strict_types=1);

namespace App\Entity;

use App\Farm\BelongsToFarm;
use App\Farm\FarmScoped;
use App\Repository\InvoiceRuleRepository;
use App\Validator\ValidNip;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Reguła automatyczna: faktury kosztowe od danego NIP od razu jako „tylko koszt”.
 */
#[ORM\Entity(repositoryClass: InvoiceRuleRepository::class)]
#[ORM\UniqueConstraint(columns: ['farm_id', 'contractor_nip'])]
#[UniqueEntity(fields: ['contractorNip'], message: 'Dla tego NIP istnieje już reguła.')]
class InvoiceRule implements FarmScoped
{
    use BelongsToFarm;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 10)]
    #[Assert\NotBlank]
    #[ValidNip]
    private string $contractorNip = '';

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(onDelete: 'SET NULL')]
    private ?CostCenter $costCenter = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(onDelete: 'SET NULL')]
    private ?CostCategory $costCategory = null;

    #[ORM\Column]
    private bool $active = true;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $note = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getContractorNip(): string
    {
        return $this->contractorNip;
    }

    public function setContractorNip(string $contractorNip): static
    {
        $this->contractorNip = $contractorNip;

        return $this;
    }

    public function getCostCenter(): ?CostCenter
    {
        return $this->costCenter;
    }

    public function setCostCenter(?CostCenter $costCenter): static
    {
        $this->costCenter = $costCenter;

        return $this;
    }

    public function getCostCategory(): ?CostCategory
    {
        return $this->costCategory;
    }

    public function setCostCategory(?CostCategory $costCategory): static
    {
        $this->costCategory = $costCategory;

        return $this;
    }

    public function isActive(): bool
    {
        return $this->active;
    }

    public function setActive(bool $active): static
    {
        $this->active = $active;

        return $this;
    }

    public function getNote(): ?string
    {
        return $this->note;
    }

    public function setNote(?string $note): static
    {
        $this->note = $note;

        return $this;
    }
}
