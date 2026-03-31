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

    /** @return OrderDTO[] */
    public function listAll(): array
    {
        return array_map(
            fn(Order $o) => OrderDTO::fromEntity($o),
            $this->orderRepository->findAll()
        );
    }

    public function findEntityById(int $id): ?Order
    {
        return $this->orderRepository->findById($id);
    }

    public function confirm(int $id): OrderDTO
    {
        $order = $this->orderRepository->findById($id);
        if ($order === null) {
            throw new \DomainException('Commande introuvable.');
        }
        $order->confirm();
        $this->orderRepository->save($order);
        return OrderDTO::fromEntity($order);
    }

    public function markReady(int $id): OrderDTO
    {
        $order = $this->orderRepository->findById($id);
        if ($order === null) {
            throw new \DomainException('Commande introuvable.');
        }
        $order->markReady();
        $this->orderRepository->save($order);
        return OrderDTO::fromEntity($order);
    }

    public function complete(int $id): OrderDTO
    {
        $order = $this->orderRepository->findById($id);
        if ($order === null) {
            throw new \DomainException('Commande introuvable.');
        }
        $order->complete();
        $this->orderRepository->save($order);
        return OrderDTO::fromEntity($order);
    }

    public function cancel(int $id): OrderDTO
    {
        $order = $this->orderRepository->findById($id);
        if ($order === null) {
            throw new \DomainException('Commande introuvable.');
        }
        $order->cancel();
        $this->orderRepository->save($order);
        return OrderDTO::fromEntity($order);
    }

    public function delete(int $id): void
    {
        $order = $this->orderRepository->findById($id);
        if ($order === null) {
            throw new \DomainException('Commande introuvable.');
        }
        $this->orderRepository->remove($order);
    }
}
