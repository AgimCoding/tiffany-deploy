<?php

declare(strict_types=1);

namespace App\Domain\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'services')]
class Service
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\Column(type: 'string', length: 255)]
    private string $name;

    #[ORM\Column(type: 'string', length: 100)]
    private string $price;

    #[ORM\Column(type: 'integer')]
    private int $durationMin;

    #[ORM\Column(type: 'string', length: 500)]
    private string $description;

    #[ORM\Column(type: 'boolean')]
    private bool $isQuote;

    #[ORM\Column(type: 'string', length: 50, nullable: true)]
    private ?string $category = null;

    #[ORM\Column(type: 'integer')]
    private int $sortOrder = 0;

    public function __construct(
        string $name,
        string $price,
        int $durationMin,
        string $description,
        bool $isQuote = false
    ) {
        $this->name = $name;
        $this->price = $price;
        $this->durationMin = $durationMin;
        $this->description = $description;
        $this->isQuote = $isQuote;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): void
    {
        $this->name = $name;
    }

    public function getPrice(): string
    {
        return $this->price;
    }

    public function setPrice(string $price): void
    {
        $this->price = $price;
    }

    public function getDurationMin(): int
    {
        return $this->durationMin;
    }

    public function setDurationMin(int $durationMin): void
    {
        $this->durationMin = $durationMin;
    }

    public function getDescription(): string
    {
        return $this->description;
    }

    public function setDescription(string $description): void
    {
        $this->description = $description;
    }

    public function isQuote(): bool
    {
        return $this->isQuote;
    }

    public function setIsQuote(bool $isQuote): void
    {
        $this->isQuote = $isQuote;
    }

    public function getSortOrder(): int
    {
        return $this->sortOrder;
    }

    public function setSortOrder(int $sortOrder): void
    {
        $this->sortOrder = $sortOrder;
    }

    public function getCategory(): ?string
    {
        return $this->category;
    }

    public function setCategory(?string $category): void
    {
        $this->category = $category;
    }
}
