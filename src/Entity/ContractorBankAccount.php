<?php

declare(strict_types=1);

namespace App\Entity;

use App\Enum\DataSource;
use App\Repository\ContractorBankAccountRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ContractorBankAccountRepository::class)]
class ContractorBankAccount
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 34)]
    private string $number;

    #[ORM\Column(nullable: true)]
    private ?bool $onWhiteList = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $checkedAt = null;

    public function __construct(
        #[ORM\ManyToOne(inversedBy: 'bankAccounts')]
        #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
        private Contractor $contractor,
        string $number,
        #[ORM\Column(length: 12, enumType: DataSource::class)]
        private DataSource $source,
    ) {
        $this->number = self::normalize($number);
    }

    public static function normalize(string $number): string
    {
        return strtoupper(preg_replace('/[\s-]+/', '', $number) ?? $number);
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getContractor(): Contractor
    {
        return $this->contractor;
    }

    public function getNumber(): string
    {
        return $this->number;
    }

    public function getFormattedNumber(): string
    {
        $number = $this->number;
        $prefix = '';
        if (preg_match('/^[A-Z]{2}/', $number)) {
            $prefix = substr($number, 0, 2).' ';
            $number = substr($number, 2);
        }
        if (26 === \strlen($number)) {
            return $prefix.substr($number, 0, 2).' '.implode(' ', str_split(substr($number, 2), 4));
        }

        return $prefix.$number;
    }

    public function getSource(): DataSource
    {
        return $this->source;
    }

    public function isOnWhiteList(): ?bool
    {
        return $this->onWhiteList;
    }

    public function markWhiteList(bool $onList): void
    {
        $this->onWhiteList = $onList;
        $this->checkedAt = new \DateTimeImmutable();
    }

    public function getCheckedAt(): ?\DateTimeImmutable
    {
        return $this->checkedAt;
    }
}
