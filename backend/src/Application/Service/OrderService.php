<?php

declare(strict_types=1);

namespace App\Application\Service;

use App\Application\DTO\OrderDTO;
use App\Domain\Entity\Order;
use App\Domain\Entity\User;
use App\Domain\Port\OrderRepositoryInterface;
use App\Domain\Port\ProductRepositoryInterface;

final class OrderService
{
    public function __construct(
        private readonly OrderRepositoryInterface $orderRepository,
        private readonly ProductRepositoryInterface $productRepository,
    ) {
    }

    /**
     * @param array<array{productId: int, quantity: int}> $items
     */
    public function create(User $user, array $items): OrderDTO
    {
        $order = new Order($user);

        foreach ($items as $itemData) {
            $product = $this->productRepository->findById($itemData['productId']);
            if ($product === null) {
                throw new \DomainException("Produit #{$itemData['productId']} introuvable.");
            }
            $order->addItem($product, $itemData['quantity']);
        }

        $this->orderRepository->save($order);

        return OrderDTO::fromEntity($order);
    }

    /** @return OrderDTO[] */
    public function listForUser(User $user): array
    {
        return array_map(
            fn(Order $o) => OrderDTO::fromEntity($o),
            $this->orderRepository->findByUser($user->getId())
        );
    }
}
