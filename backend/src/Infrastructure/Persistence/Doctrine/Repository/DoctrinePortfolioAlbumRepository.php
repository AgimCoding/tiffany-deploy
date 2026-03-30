<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Doctrine\Repository;

use App\Domain\Entity\PortfolioAlbum;
use App\Domain\Port\PortfolioAlbumRepositoryInterface;
use Doctrine\ORM\EntityManagerInterface;

final class DoctrinePortfolioAlbumRepository implements PortfolioAlbumRepositoryInterface
{
    public function __construct(private readonly EntityManagerInterface $em)
    {
    }

    public function findById(int $id): ?PortfolioAlbum
    {
        return $this->em->find(PortfolioAlbum::class, $id);
    }

    public function findAllOrdered(): array
    {
        return $this->em->getRepository(PortfolioAlbum::class)->findBy([], ['sortOrder' => 'ASC', 'id' => 'ASC']);
    }

    public function save(PortfolioAlbum $album): void
    {
        $this->em->persist($album);
        $this->em->flush();
    }

    public function remove(PortfolioAlbum $album): void
    {
        $this->em->remove($album);
        $this->em->flush();
    }
}
