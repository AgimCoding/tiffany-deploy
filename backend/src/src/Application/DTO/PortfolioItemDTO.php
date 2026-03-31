<?php

declare(strict_types=1);

namespace App\Application\DTO;

use App\Domain\Entity\PortfolioItem;

final class PortfolioItemDTO
{
    public function __construct(
        public readonly int $id,
        public readonly string $title,
        public readonly ?string $description,
        public readonly string $imageUrl,
        public readonly int $sortOrder,
        public readonly ?int $albumId,
        public readonly ?string $albumName,
        public readonly bool $isFeatured,
    ) {
    }

    public static function fromEntity(PortfolioItem $item): self
    {
        return new self(
            id: $item->getId(),
            title: $item->getTitle(),
            description: $item->getDescription(),
            imageUrl: $item->getImageUrl(),
            sortOrder: $item->getSortOrder(),
            albumId: $item->getAlbum()?->getId(),
            albumName: $item->getAlbum()?->getName(),
            isFeatured: $item->isFeatured(),
        );
    }
}
