<?php

declare(strict_types=1);

namespace App\Entity;

use App\Market\Domain\Product;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'products')]
#[ORM\Index(columns: ['category'], name: 'idx_products_category')]
#[ORM\Index(columns: ['family_slug'], name: 'idx_products_family_slug')]
class ProductEntity
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: Types::INTEGER)]
    private ?int $id = null;

    #[ORM\Column(type: Types::STRING, length: 120, unique: true)]
    private string $slug;

    #[ORM\Column(type: Types::STRING, length: 255)]
    private string $name;

    #[ORM\Column(type: Types::TEXT)]
    private string $definition;

    #[ORM\Column(type: Types::STRING, length: 50)]
    private string $category;

    #[ORM\Column(type: Types::STRING, length: 120)]
    private string $familySlug;

    #[ORM\Column(type: Types::STRING, length: 255)]
    private string $familyName;

    #[ORM\Column(type: Types::STRING, length: 512, nullable: true)]
    private ?string $image = null;

    #[ORM\Column(type: Types::STRING, length: 255, nullable: true)]
    private ?string $imageCredit = null;

    #[ORM\Column(type: Types::STRING, length: 1024, nullable: true)]
    private ?string $imageSource = null;

    /** @var array<string, mixed> */
    #[ORM\Column(type: Types::JSON)]
    private array $specifications = [];

    #[ORM\Column(type: Types::DATETIMETZ_IMMUTABLE)]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(type: Types::DATETIMETZ_IMMUTABLE)]
    private \DateTimeImmutable $updatedAt;

    /** @param array<string, mixed> $specifications */
    public function __construct(
        string $slug,
        string $name,
        string $definition,
        string $category,
        string $familySlug,
        string $familyName,
        ?string $image = null,
        ?string $imageCredit = null,
        ?string $imageSource = null,
        array $specifications = [],
        ?\DateTimeImmutable $createdAt = null,
        ?\DateTimeImmutable $updatedAt = null,
    ) {
        $this->slug = $slug;
        $this->name = $name;
        $this->definition = $definition;
        $this->category = $category;
        $this->familySlug = $familySlug;
        $this->familyName = $familyName;
        $this->image = $image;
        $this->imageCredit = $imageCredit;
        $this->imageSource = $imageSource;
        $this->specifications = $specifications;
        $this->createdAt = $createdAt ?? new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
        $this->updatedAt = $updatedAt ?? new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getSlug(): string
    {
        return $this->slug;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getDefinition(): string
    {
        return $this->definition;
    }

    public function getCategory(): string
    {
        return $this->category;
    }

    public function getFamilySlug(): string
    {
        return $this->familySlug;
    }

    public function getFamilyName(): string
    {
        return $this->familyName;
    }

    public function getImage(): ?string
    {
        return $this->image;
    }

    public function getImageCredit(): ?string
    {
        return $this->imageCredit;
    }

    public function getImageSource(): ?string
    {
        return $this->imageSource;
    }

    /** @return array<string, mixed> */
    public function getSpecifications(): array
    {
        return $this->specifications;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): \DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function updateFromProduct(Product $product): void
    {
        $this->name = $product->name;
        $this->definition = $product->definition;
        $this->category = $product->category;
        if ($product->familySlug !== null && $product->familySlug !== '') {
            $this->familySlug = $product->familySlug;
        }
        if ($product->familyName !== null && $product->familyName !== '') {
            $this->familyName = $product->familyName;
        }
        if ($product->image !== null && $product->image !== '') {
            $this->image = $product->image;
        }
        if ($product->imageCredit !== null && $product->imageCredit !== '') {
            $this->imageCredit = $product->imageCredit;
        }
        if ($product->imageSource !== null && $product->imageSource !== '') {
            $this->imageSource = $product->imageSource;
        }
        $this->specifications = $product->specifications;
        $this->updatedAt = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
    }

    public function toDomain(): Product
    {
        return new Product(
            $this->slug,
            $this->name,
            $this->definition,
            $this->category,
            $this->specifications,
            $this->familySlug,
            $this->familyName,
            $this->image,
            $this->imageCredit,
            $this->imageSource,
        );
    }
}
