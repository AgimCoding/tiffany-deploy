<?php

declare(strict_types=1);

namespace App\Application\DTO;

use App\Domain\Entity\Order;

final class OrderDTO
{
    public function __construct(
        public readonly int $id,
        public readonly string $total,
        public readonly string $status,
        public readonly string $createdAt,
        public readonly array $items,
    ) {
    }

    public static function fromEntity(Order $order): self
    {
        $items = [];
        foreach ($order->getItems() as $item) {
            $items[] = [
                'id' => $item->getId(),
                'productName' => $item->getProduct()->getName(),
                'quantity' => $item->getQuantity(),
                'price' => $item->getPrice(),
            ];
        }

        return new self(
            id: $order->getId(),
            total: $order->getTotal(),
            status: $order->getStatus(),
            createdAt: $order->getCreatedAt()->format('c'),
            items: $items,
        );
    }
}
