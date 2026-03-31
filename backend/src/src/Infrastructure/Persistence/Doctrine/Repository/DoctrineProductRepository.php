<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Doctrine\Repository;

use App\Domain\Entity\Product;
use App\Domain\Port\ProductRepositoryInterface;
use Doctrine\ORM\EntityManagerInterface;

final class DoctrineProductRepository implements ProductRepositoryInterface
{
    public function __construct(private readonly EntityManagerInterface $em)
    {
    }

    public function findById(int $id): ?Product
    {
        return $this->em->find(Product::class, $id);
    }

    public function findAllActive(): array
    {
        return $this->em->getRepository(Product::class)->findBy(
            ['active' => true],
            ['sortOrder' => 'ASC', 'id' => 'ASC']
        );
    }

    public function save(Product $product): void
    {
        $this->em->persist($product);
        $this->em->flush();
    }

    public function remove(Product $product): void
    {
        $this->em->remove($product);
        $this->em->flush();
    }
}
