<?php

declare(strict_types=1);

namespace App\Domain\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'portfolio_items')]
class PortfolioItem
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\Column(type: 'string', length: 255)]
    private string $title;

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    private ?string $description = null;

    #[ORM\Column(type: 'string', length: 500)]
    private string $imageUrl;

    #[ORM\Column(type: 'integer')]
    private int $sortOrder = 0;

    #[ORM\ManyToOne(targetEntity: PortfolioAlbum::class)]
    #[ORM\JoinColumn(name: 'album_id', nullable: true, onDelete: 'SET NULL')]
    private ?PortfolioAlbum $album = null;

    #[ORM\Column(type: 'boolean')]
    private bool $isFeatured = false;

    public function __construct(string $title, string $imageUrl)
    {
        $this->title = $title;
        $this->imageUrl = $imageUrl;
    }

    public function getId(): ?int { return $this->id; }
    public function getTitle(): string { return $this->title; }
    public function setTitle(string $title): void { $this->title = $title; }
    public function getDescription(): ?string { return $this->description; }
    public function setDescription(?string $description): void { $this->description = $description; }
    public function getImageUrl(): string { return $this->imageUrl; }
    public function setImageUrl(string $imageUrl): void { $this->imageUrl = $imageUrl; }
    public function getSortOrder(): int { return $this->sortOrder; }
    public function setSortOrder(int $sortOrder): void { $this->sortOrder = $sortOrder; }
    public function getAlbum(): ?PortfolioAlbum { return $this->album; }
    public function setAlbum(?PortfolioAlbum $album): void { $this->album = $album; }
    public function isFeatured(): bool { return $this->isFeatured; }
    public function setIsFeatured(bool $isFeatured): void { $this->isFeatured = $isFeatured; }
}
