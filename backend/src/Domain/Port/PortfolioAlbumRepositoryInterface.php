<?php

declare(strict_types=1);

namespace App\Domain\Port;

use App\Domain\Entity\PortfolioAlbum;

interface PortfolioAlbumRepositoryInterface
{
    public function findById(int $id): ?PortfolioAlbum;
    /** @return PortfolioAlbum[] */
    public function findAllOrdered(): array;
    public function save(PortfolioAlbum $album): void;
    public function remove(PortfolioAlbum $album): void;
}
