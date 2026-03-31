<?php

declare(strict_types=1);

namespace App\Domain\Port;

use App\Domain\Entity\PortfolioItem;

interface PortfolioItemRepositoryInterface
{
    public function findById(int $id): ?PortfolioItem;

    /** @return PortfolioItem[] */
    public function findAllOrdered(): array;

    /** @return PortfolioItem[] */
    public function findByAlbum(int $albumId): array;

    public function save(PortfolioItem $item): void;

    public function remove(PortfolioItem $item): void;
}
