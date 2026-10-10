<?php

declare(strict_types=1);

namespace App\Entity;

use App\Enum\ContractorKind;
use App\Enum\DataSource;
use App\Farm\BelongsToFarm;
use App\Farm\FarmScoped;
use App\Repository\ContractorRepository;
use App\Validator\ValidNip;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: ContractorRepository::class)]
#[ORM\Index(columns: ['farm_id', 'name'])]
#[ORM\UniqueConstraint(columns: ['farm_id', 'nip'])]
#[UniqueEntity(fields: ['nip'], message: 'Kontrahent z tym NIP już istnieje.')]
class Contractor implements \Stringable, FarmScoped
{
    use BelongsToFarm;

    public const array ADDRESS_FIELDS = ['street', 'postalCode', 'city', 'country'];

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 10, nullable: true)]
    #[ValidNip]
    private ?string $nip = null;

    #[ORM\Column(length: 512)]
    #[Assert\NotBlank]
    private string $name = '';

    #[ORM\Column(length: 100, nullable: true)]
    private ?string $shortName = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $street = null;

    #[ORM\Column(length: 10, nullable: true)]
    private ?string $postalCode = null;

    #[ORM\Column(length: 120, nullable: true)]
    private ?string $city = null;

    #[ORM\Column(length: 2)]
    private string $country = 'PL';

    #[ORM\Column(length: 180, nullable: true)]
    #[Assert\Email]
    private ?string $email = null;

    #[ORM\Column(length: 40, nullable: true)]
    private ?string $phone = null;

    #[ORM\Column(length: 120, nullable: true)]
    private ?string $contactPerson = null;

    #[ORM\Column(length: 10, nullable: true, enumType: ContractorKind::class)]
    private ?ContractorKind $kind = null;

    #[ORM\Column(nullable: true)]
    #[Assert\Range(min: 0, max: 365)]
    private ?int $deliveryDays = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $notes = null;

    #[ORM\Column(length: 12, enumType: DataSource::class)]
    private DataSource $dataSource = DataSource::Manual;

    #[ORM\ManyToOne(targetEntity: Invoice::class)]
    #[ORM\JoinColumn(onDelete: 'SET NULL')]
    private ?Invoice $dataFromInvoice = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $dataUpdatedAt = null;

    #[ORM\Column]
    private bool $updateLocked = false;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $whiteListCheckedAt = null;

    #[ORM\Column]
    private bool $active = true;

    /** @var Collection<int, ContractorBankAccount> */
    #[ORM\OneToMany(targetEntity: ContractorBankAccount::class, mappedBy: 'contractor', cascade: ['persist'], orphanRemoval: true)]
    private Collection $bankAccounts;

    public function __construct()
    {
        $this->bankAccounts = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getNip(): ?string
    {
        return $this->nip;
    }

    public function setNip(?string $nip): static
    {
        $this->nip = $nip;

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

    public function getShortName(): ?string
    {
        return $this->shortName;
    }

    public function setShortName(?string $shortName): static
    {
        $this->shortName = $shortName;

        return $this;
    }

    public function getDisplayName(): string
    {
        return $this->shortName ?: $this->name;
    }

    public function getStreet(): ?string
    {
        return $this->street;
    }

    public function setStreet(?string $street): static
    {
        $this->street = $street;

        return $this;
    }

    public function getPostalCode(): ?string
    {
        return $this->postalCode;
    }

    public function setPostalCode(?string $postalCode): static
    {
        $this->postalCode = $postalCode;

        return $this;
    }

    public function getCity(): ?string
    {
        return $this->city;
    }

    public function setCity(?string $city): static
    {
        $this->city = $city;

        return $this;
    }

    public function getCountry(): string
    {
        return $this->country;
    }

    public function setCountry(string $country): static
    {
        $this->country = strtoupper($country);

        return $this;
    }

    public function hasAddress(): bool
    {
        return null !== $this->street || null !== $this->city;
    }

    public function getAddressLine(): string
    {
        $cityLine = trim(($this->postalCode ?? '').' '.($this->city ?? ''));

        return implode(', ', array_filter([$this->street, $cityLine, 'PL' !== $this->country ? $this->country : null]));
    }

    public function getEmail(): ?string
    {
        return $this->email;
    }

    public function setEmail(?string $email): static
    {
        $this->email = $email;

        return $this;
    }

    public function getPhone(): ?string
    {
        return $this->phone;
    }

    public function setPhone(?string $phone): static
    {
        $this->phone = $phone;

        return $this;
    }

    public function getContactPerson(): ?string
    {
        return $this->contactPerson;
    }

    public function setContactPerson(?string $contactPerson): static
    {
        $this->contactPerson = $contactPerson;

        return $this;
    }

    public function getKind(): ?ContractorKind
    {
        return $this->kind;
    }

    public function setKind(?ContractorKind $kind): static
    {
        $this->kind = $kind;

        return $this;
    }

    public function getDeliveryDays(): ?int
    {
        return $this->deliveryDays;
    }

    public function setDeliveryDays(?int $deliveryDays): static
    {
        $this->deliveryDays = $deliveryDays;

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

    public function getDataSource(): DataSource
    {
        return $this->dataSource;
    }

    public function setDataSource(DataSource $dataSource): static
    {
        $this->dataSource = $dataSource;

        return $this;
    }

    public function getDataFromInvoice(): ?Invoice
    {
        return $this->dataFromInvoice;
    }

    public function setDataFromInvoice(?Invoice $invoice): static
    {
        $this->dataFromInvoice = $invoice;

        return $this;
    }

    public function getDataUpdatedAt(): ?\DateTimeImmutable
    {
        return $this->dataUpdatedAt;
    }

    public function touchData(): static
    {
        $this->dataUpdatedAt = new \DateTimeImmutable();

        return $this;
    }

    public function isUpdateLocked(): bool
    {
        return $this->updateLocked;
    }

    public function setUpdateLocked(bool $updateLocked): static
    {
        $this->updateLocked = $updateLocked;

        return $this;
    }

    public function getWhiteListCheckedAt(): ?\DateTimeImmutable
    {
        return $this->whiteListCheckedAt;
    }

    public function setWhiteListCheckedAt(?\DateTimeImmutable $checkedAt): static
    {
        $this->whiteListCheckedAt = $checkedAt;

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

    /**
     * @return Collection<int, ContractorBankAccount>
     */
    public function getBankAccounts(): Collection
    {
        return $this->bankAccounts;
    }

    public function findBankAccount(string $number): ?ContractorBankAccount
    {
        $normalized = ContractorBankAccount::normalize($number);
        foreach ($this->bankAccounts as $account) {
            if ($account->getNumber() === $normalized) {
                return $account;
            }
        }

        return null;
    }

    public function addBankAccount(ContractorBankAccount $account): static
    {
        if (!$this->bankAccounts->contains($account)) {
            $this->bankAccounts->add($account);
        }

        return $this;
    }

    public function __toString(): string
    {
        return $this->getDisplayName();
    }
}
