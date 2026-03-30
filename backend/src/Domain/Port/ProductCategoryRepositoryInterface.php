<?php

declare(strict_types=1);

namespace App\Domain\Port;

use App\Domain\Entity\ProductCategory;

interface ProductCategoryRepositoryInterface
{
    public function findById(int $id): ?ProductCategory;

    /** @return ProductCategory[] */
    public function findAll(): array;

    public function save(ProductCategory $category): void;

    public function remove(ProductCategory $category): void;
}
