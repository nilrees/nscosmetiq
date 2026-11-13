<?php

namespace App\Entity;

use App\Repository\ProductRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: ProductRepository::class)]
class Product
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    // PRODUCT NAME
    #[ORM\Column(length: 100)]
    #[Assert\NotBlank(message: 'Product name is required.')]
    #[Assert\Length(
        min: 3,
        max: 100,
        minMessage: 'Product name must be at least {{ limit }} characters.',
        maxMessage: 'Product name cannot exceed {{ limit }} characters.'
    )]
    private ?string $name = null;

    // DESCRIPTION
    #[ORM\Column(type: Types::TEXT, nullable: true)]
    #[Assert\Length(
        max: 1000,
        maxMessage: 'Description cannot exceed {{ limit }} characters.'
    )]
    private ?string $description = null;

    // CATEGORY
    #[ORM\Column(length: 100)]
    #[Assert\NotBlank(message: 'Product category is required.')]
    #[Assert\Length(
        max: 100,
        maxMessage: 'Category cannot exceed {{ limit }} characters.'
    )]
    private ?string $category = null;

    // PRODUCT IMAGE
    #[ORM\Column(length: 255, nullable: true)]
    private ?string $imageFilename = null;

    // PRODUCT PRICE
    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2)]
    #[Assert\NotNull(message: 'Product price is required.')]
    #[Assert\Positive(message: 'Product price must be greater than zero.')]
    private ?string $price = null;

    // STOCK QUANTITY
    #[ORM\Column]
    #[Assert\NotNull(message: 'Stock quantity is required.')]
    #[Assert\PositiveOrZero(message: 'Stock quantity cannot be negative.')]
    private ?int $stock = null;

    // DATE CREATED
    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private ?\DateTimeImmutable $createdAt = null;

    // PRODUCT OWNER
    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private ?User $createdBy = null;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
    }

    // GET PRODUCT ID
    public function getId(): ?int
    {
        return $this->id;
    }

    // GET PRODUCT NAME
    public function getName(): ?string
    {
        return $this->name;
    }

    // SET PRODUCT NAME
    public function setName(string $name): static
    {
        $this->name = $name;
        return $this;
    }

    // GET DESCRIPTION
    public function getDescription(): ?string
    {
        return $this->description;
    }

    // SET DESCRIPTION
    public function setDescription(?string $description): static
    {
        $this->description = $description;
        return $this;
    }

    // GET CATEGORY
    public function getCategory(): ?string
    {
        return $this->category;
    }

    // SET CATEGORY
    public function setCategory(string $category): static
    {
        $this->category = $category;
        return $this;
    }

    // GET PRODUCT IMAGE
    public function getImageFilename(): ?string
    {
        return $this->imageFilename;
    }

    // SET PRODUCT IMAGE
    public function setImageFilename(?string $imageFilename): static
    {
        $this->imageFilename = $imageFilename;
        return $this;
    }

    // GET PRODUCT PRICE
    public function getPrice(): ?string
    {
        return $this->price;
    }

    // SET PRODUCT PRICE
    public function setPrice(string $price): static
    {
        $this->price = $price;
        return $this;
    }

    // GET STOCK QUANTITY
    public function getStock(): ?int
    {
        return $this->stock;
    }

    // SET STOCK QUANTITY
    public function setStock(int $stock): static
    {
        if ($stock < 0) {
            throw new \InvalidArgumentException(
                'Stock quantity cannot be negative.'
            );
        }

        $this->stock = $stock;

        return $this;
    }

    // GET INVENTORY STATUS
    public function getInventoryStatus(): string
    {
        if ($this->stock === null) {
            return 'Unknown';
        }

        if ($this->stock === 0) {
            return 'Out of Stock';
        }

        if ($this->stock <= 5) {
            return 'Low Stock';
        }

        return 'In Stock';
    }

    // CHECK IF PRODUCT HAS LOW STOCK
    public function isLowStock(): bool
    {
        return $this->stock !== null
            && $this->stock > 0
            && $this->stock <= 5;
    }

    // CHECK IF PRODUCT IS OUT OF STOCK
    public function isOutOfStock(): bool
    {
        return $this->stock === 0;
    }

    // GET DATE CREATED
    public function getCreatedAt(): ?\DateTimeImmutable
    {
        return $this->createdAt;
    }

    // SET DATE CREATED
    public function setCreatedAt(\DateTimeImmutable $createdAt): static
    {
        $this->createdAt = $createdAt;
        return $this;
    }

    // GET PRODUCT OWNER
    public function getCreatedBy(): ?User
    {
        return $this->createdBy;
    }

    // SET PRODUCT OWNER
    public function setCreatedBy(?User $createdBy): static
    {
        $this->createdBy = $createdBy;
        return $this;
    }
}
