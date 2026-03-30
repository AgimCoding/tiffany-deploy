<?php

declare(strict_types=1);

namespace App\Application\Service;

use App\Application\DTO\ProductCategoryDTO;
use App\Domain\Entity\ProductCategory;
use App\Domain\Port\ProductCategoryRepositoryInterface;

final class ProductCategoryService
{
    public function __construct(
        private readonly ProductCategoryRepositoryInterface $repo,
    ) {
    }

    /** @return ProductCategoryDTO[] */
    public function listAll(): array
    {
        return array_map(
            fn(ProductCategory $c) => ProductCategoryDTO::fromEntity($c),
            $this->repo->findAll()
        );
    }

    public function create(string $name, int $sortOrder = 0): ProductCategoryDTO
    {
        $cat = new ProductCategory($name, $sortOrder);
        $this->repo->save($cat);
        return ProductCategoryDTO::fromEntity($cat);
    }

    public function update(int $id, array $data): ProductCategoryDTO
    {
        $cat = $this->repo->findById($id);
        if ($cat === null) {
            throw new \DomainException('Catégorie introuvable.');
        }

        if (isset($data['name'])) $cat->setName($data['name']);
        if (isset($data['sortOrder'])) $cat->setSortOrder($data['sortOrder']);

        $this->repo->save($cat);
        return ProductCategoryDTO::fromEntity($cat);
    }

    public function delete(int $id): void
    {
        $cat = $this->repo->findById($id);
        if ($cat === null) {
            throw new \DomainException('Catégorie introuvable.');
        }
        $this->repo->remove($cat);
    }
}
