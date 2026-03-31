<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Doctrine\Repository;

use App\Domain\Entity\Order;
use App\Domain\Port\OrderRepositoryInterface;
use Doctrine\ORM\EntityManagerInterface;

final class DoctrineOrderRepository implements OrderRepositoryInterface
{
    public function __construct(private readonly EntityManagerInterface $em)
    {
    }

    public function findById(int $id): ?Order
    {
        return $this->em->find(Order::class, $id);
    }

    public function findByUser(int $userId): array
    {
        return $this->em->getRepository(Order::class)->findBy(
            ['user' => $userId],
            ['createdAt' => 'DESC']
        );
    }

    public function findAll(): array
    {
        return $this->em->getRepository(Order::class)->findBy([], ['createdAt' => 'DESC']);
    }

    public function save(Order $order): void
    {
        $this->em->persist($order);
        $this->em->flush();
    }

    public function remove(Order $order): void
    {
        $this->em->remove($order);
        $this->em->flush();
    }
}
