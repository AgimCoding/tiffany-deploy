<?php

declare(strict_types=1);

namespace App\Application\DTO;

use App\Domain\Entity\PortfolioAlbum;

final class PortfolioAlbumDTO
{
    public function __construct(
        public readonly int $id,
        public readonly string $name,
        public readonly ?string $description,
        public readonly ?string $coverImageUrl,
        public readonly int $sortOrder,
        public readonly int $photoCount = 0,
    ) {
    }

    public static function fromEntity(PortfolioAlbum $album, int $photoCount = 0): self
    {
        return new self(
            id: $album->getId(),
            name: $album->getName(),
            description: $album->getDescription(),
            coverImageUrl: $album->getCoverImageUrl(),
            sortOrder: $album->getSortOrder(),
            photoCount: $photoCount,
        );
    }
}
