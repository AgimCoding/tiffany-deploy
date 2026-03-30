<?php

declare(strict_types=1);

namespace App\Application\DTO;

use App\Domain\Entity\Product;

final class ProductDTO
{
    public function __construct(
        public readonly int $id,
        public readonly string $name,
        public readonly string $price,
        public readonly string $imageUrl,
        public readonly ?string $description,
        public readonly int $stock,
        public readonly ?int $categoryId,
        public readonly ?string $categoryName,
        public readonly int $sortOrder,
    ) {
    }

    public static function fromEntity(Product $product): self
    {
        return new self(
            id: $product->getId(),
            name: $product->getName(),
            price: $product->getPrice(),
            imageUrl: $product->getImageUrl(),
            description: $product->getDescription(),
            stock: $product->getStock(),
            categoryId: $product->getCategory()?->getId(),
            categoryName: $product->getCategory()?->getName(),
            sortOrder: $product->getSortOrder(),
        );
    }
}
