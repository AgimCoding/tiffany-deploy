<?php

declare(strict_types=1);

namespace App\Domain\Port;

use App\Domain\Entity\Order;

interface OrderRepositoryInterface
{
    public function findById(int $id): ?Order;

    /** @return Order[] */
    public function findByUser(int $userId): array;

    /** @return Order[] */
    public function findAll(): array;

    public function save(Order $order): void;

    public function remove(Order $order): void;
}
