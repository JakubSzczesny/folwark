<?php

declare(strict_types=1);

namespace App\Entity;

use App\Enum\DataSource;
use App\Repository\ContractorHistoryRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ContractorHistoryRepository::class)]
class ContractorHistory
{
    public const array FIELD_LABELS = [
        'name' => 'Nazwa',
        'shortName' => 'Nazwa skrócona',
        'street' => 'Ulica',
        'postalCode' => 'Kod pocztowy',
        'city' => 'Miejscowość',
        'country' => 'Kraj',
        'email' => 'E-mail',
        'phone' => 'Telefon',
        'contactPerson' => 'Osoba kontaktowa',
        'nip' => 'NIP',
        'updateLocked' => 'Blokada aktualizacji',
        'bankAccount' => 'Rachunek bankowy',
    ];

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $changedAt;

    public function __construct(
        #[ORM\ManyToOne]
        #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
        private Contractor $contractor,
        #[ORM\Column(length: 40)]
        private string $field,
        #[ORM\Column(type: Types::TEXT, nullable: true)]
        private ?string $oldValue,
        #[ORM\Column(type: Types::TEXT, nullable: true)]
        private ?string $newValue,
        #[ORM\Column(length: 12, enumType: DataSource::class)]
        private DataSource $source,
        #[ORM\ManyToOne]
        #[ORM\JoinColumn(onDelete: 'SET NULL')]
        private ?Invoice $invoice = null,
        #[ORM\ManyToOne]
        #[ORM\JoinColumn(onDelete: 'SET NULL')]
        private ?User $user = null,
    ) {
        $this->changedAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getContractor(): Contractor
    {
        return $this->contractor;
    }

    public function getField(): string
    {
        return $this->field;
    }

    public function getFieldLabel(): string
    {
        return self::FIELD_LABELS[$this->field] ?? $this->field;
    }

    public function getOldValue(): ?string
    {
        return $this->oldValue;
    }

    public function getNewValue(): ?string
    {
        return $this->newValue;
    }

    public function getSource(): DataSource
    {
        return $this->source;
    }

    public function getInvoice(): ?Invoice
    {
        return $this->invoice;
    }

    public function getUser(): ?User
    {
        return $this->user;
    }

    public function getChangedAt(): \DateTimeImmutable
    {
        return $this->changedAt;
    }
}
