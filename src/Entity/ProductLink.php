<?php

declare(strict_types=1);

namespace App\Entity;

use App\Farm\BelongsToFarm;
use App\Farm\FarmScoped;
use App\Repository\ProductLinkRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: ProductLinkRepository::class)]
class ProductLink implements FarmScoped
{
    use BelongsToFarm;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 1024)]
    #[Assert\NotBlank(message: 'Wklej adres.')]
    #[Assert\Url(message: 'To nie wygląda na adres strony (np. https://…).', requireTld: true)]
    #[Assert\Length(max: 1024)]
    private string $url = '';

    #[ORM\Column(length: 160)]
    #[Assert\Length(max: 160)]
    private string $name = '';

    #[ORM\Column(length: 255, nullable: true)]
    #[Assert\Length(max: 255)]
    private ?string $description = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(onDelete: 'SET NULL')]
    private ?User $addedBy = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $addedAt;

    public function __construct(
        #[ORM\ManyToOne]
        #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
        private Product $product,
        #[ORM\ManyToOne]
        #[ORM\JoinColumn(nullable: false)]
        private ProductLinkCategory $category,
    ) {
        $this->addedAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getProduct(): Product
    {
        return $this->product;
    }

    public function getCategory(): ProductLinkCategory
    {
        return $this->category;
    }

    public function setCategory(ProductLinkCategory $category): static
    {
        $this->category = $category;

        return $this;
    }

    public function getUrl(): string
    {
        return $this->url;
    }

    public function setUrl(?string $url): static
    {
        $url = trim((string) $url);
        if ('' !== $url && !preg_match('~^https?://~i', $url)) {
            $url = 'https://'.$url;
        }
        $this->url = $url;

        return $this;
    }

    /**
     * Nazwa wyświetlana – domyślnie domena adresu.
     */
    public function getName(): string
    {
        if ('' !== $this->name) {
            return $this->name;
        }

        $host = (string) parse_url($this->url, \PHP_URL_HOST);

        return '' !== $host ? preg_replace('/^www\./', '', $host) ?? $host : $this->url;
    }

    public function setName(?string $name): static
    {
        $this->name = trim((string) $name);

        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): static
    {
        $this->description = $description;

        return $this;
    }

    public function getAddedBy(): ?User
    {
        return $this->addedBy;
    }

    public function setAddedBy(?User $addedBy): static
    {
        $this->addedBy = $addedBy;

        return $this;
    }

    public function getAddedAt(): \DateTimeImmutable
    {
        return $this->addedAt;
    }
}
