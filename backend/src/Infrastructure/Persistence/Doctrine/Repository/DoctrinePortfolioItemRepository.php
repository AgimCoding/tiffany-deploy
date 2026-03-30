<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Doctrine\Repository;

use App\Domain\Entity\PortfolioItem;
use App\Domain\Port\PortfolioItemRepositoryInterface;
use Doctrine\ORM\EntityManagerInterface;

final class DoctrinePortfolioItemRepository implements PortfolioItemRepositoryInterface
{
    public function __construct(private readonly EntityManagerInterface $em)
    {
    }

    public function findById(int $id): ?PortfolioItem
    {
        return $this->em->find(PortfolioItem::class, $id);
    }

    public function findAllOrdered(): array
    {
        return $this->em->getRepository(PortfolioItem::class)->findBy([], ['sortOrder' => 'ASC']);
    }

    public function findByAlbum(int $albumId): array
    {
        return $this->em->getRepository(PortfolioItem::class)->findBy(
            ['album' => $albumId],
            ['sortOrder' => 'ASC']
        );
    }

    public function save(PortfolioItem $item): void
    {
        $this->em->persist($item);
        $this->em->flush();
    }

    public function remove(PortfolioItem $item): void
    {
        $this->em->remove($item);
        $this->em->flush();
    }
}
