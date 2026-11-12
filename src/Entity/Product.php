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

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(string $name): static
    {
        $this->name = $name;
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

    public function getCategory(): ?string
    {
        return $this->category;
    }

    public function setCategory(string $category): static
    {
        $this->category = $category;
        return $this;
    }

    public function getImageFilename(): ?string
    {
        return $this->imageFilename;
    }

    public function setImageFilename(?string $imageFilename): static
    {
        $this->imageFilename = $imageFilename;
        return $this;
    }

    public function getPrice(): ?string
    {
        return $this->price;
    }

    public function setPrice(string $price): static
    {
        $this->price = $price;
        return $this;
    }

    public function getStock(): ?int
    {
        return $this->stock;
    }

    public function setStock(int $stock): static
    {
        $this->stock = $stock;
        return $this;
    }

    public function getCreatedAt(): ?\DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTimeImmutable $createdAt): static
    {
        $this->createdAt = $createdAt;
        return $this;
    }

    public function getCreatedBy(): ?User
    {
        return $this->createdBy;
    }

    public function setCreatedBy(?User $createdBy): static
    {
        $this->createdBy = $createdBy;
        return $this;
    }
}
