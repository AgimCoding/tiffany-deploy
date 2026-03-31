<?php

declare(strict_types=1);

namespace App\Domain\Port;

use App\Domain\Entity\Product;

interface ProductRepositoryInterface
{
    public function findById(int $id): ?Product;

    /** @return Product[] */
    public function findAllActive(): array;

    public function save(Product $product): void;

    public function remove(Product $product): void;
}
