<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Doctrine\Repository;

use App\Domain\Entity\ProductCategory;
use App\Domain\Port\ProductCategoryRepositoryInterface;
use Doctrine\ORM\EntityManagerInterface;

final class DoctrineProductCategoryRepository implements ProductCategoryRepositoryInterface
{
    public function __construct(private readonly EntityManagerInterface $em)
    {
    }

    public function findById(int $id): ?ProductCategory
    {
        return $this->em->find(ProductCategory::class, $id);
    }

    public function findAll(): array
    {
        return $this->em->getRepository(ProductCategory::class)->findBy([], ['sortOrder' => 'ASC', 'name' => 'ASC']);
    }

    public function save(ProductCategory $category): void
    {
        $this->em->persist($category);
        $this->em->flush();
    }

    public function remove(ProductCategory $category): void
    {
        $this->em->remove($category);
        $this->em->flush();
    }
}
