<?php

declare(strict_types=1);

namespace App\Application\Service;

use App\Application\DTO\ProductDTO;
use App\Domain\Entity\Product;
use App\Domain\Port\ProductCategoryRepositoryInterface;
use App\Domain\Port\ProductRepositoryInterface;

final class ProductService
{
    public function __construct(
        private readonly ProductRepositoryInterface $productRepository,
        private readonly ProductCategoryRepositoryInterface $categoryRepository,
    ) {
    }

    /** @return ProductDTO[] */
    public function listActive(): array
    {
        return array_map(
            fn(Product $p) => ProductDTO::fromEntity($p),
            $this->productRepository->findAllActive()
        );
    }

    public function create(array $data): ProductDTO
    {
        $product = new Product($data['name'], $data['price'], $data['imageUrl']);
        $product->setDescription($data['description'] ?? null);
        $product->setStock((int) ($data['stock'] ?? 0));
        $product->setSortOrder((int) ($data['sortOrder'] ?? 0));

        if (!empty($data['categoryId'])) {
            $cat = $this->categoryRepository->findById((int) $data['categoryId']);
            $product->setCategory($cat);
        }

        $this->productRepository->save($product);
        return ProductDTO::fromEntity($product);
    }

    public function update(int $id, array $data): ProductDTO
    {
        $product = $this->productRepository->findById($id);
        if ($product === null) {
            throw new \DomainException('Produit introuvable.');
        }

        if (isset($data['name'])) $product->setName($data['name']);
        if (isset($data['price'])) $product->setPrice($data['price']);
        if (isset($data['imageUrl'])) $product->setImageUrl($data['imageUrl']);
        if (isset($data['description'])) $product->setDescription($data['description']);
        if (isset($data['stock'])) $product->setStock((int) $data['stock']);
        if (isset($data['active'])) $product->setActive($data['active']);
        if (isset($data['sortOrder'])) $product->setSortOrder((int) $data['sortOrder']);

        if (array_key_exists('categoryId', $data)) {
            if ($data['categoryId']) {
                $cat = $this->categoryRepository->findById((int) $data['categoryId']);
                $product->setCategory($cat);
            } else {
                $product->setCategory(null);
            }
        }

        $this->productRepository->save($product);
        return ProductDTO::fromEntity($product);
    }

    public function delete(int $id): void
    {
        $product = $this->productRepository->findById($id);
        if ($product === null) {
            throw new \DomainException('Produit introuvable.');
        }

        $this->productRepository->remove($product);
    }
}
