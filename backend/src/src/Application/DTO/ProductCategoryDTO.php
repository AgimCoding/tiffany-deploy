<?php

declare(strict_types=1);

namespace App\Application\DTO;

use App\Domain\Entity\ProductCategory;

final class ProductCategoryDTO
{
    public function __construct(
        public readonly int $id,
        public readonly string $name,
        public readonly int $sortOrder,
    ) {
    }

    public static function fromEntity(ProductCategory $cat): self
    {
        return new self(
            id: $cat->getId(),
            name: $cat->getName(),
            sortOrder: $cat->getSortOrder(),
        );
    }
}
