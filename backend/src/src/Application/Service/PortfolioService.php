<?php

declare(strict_types=1);

namespace App\Application\Service;

use App\Application\DTO\PortfolioAlbumDTO;
use App\Application\DTO\PortfolioItemDTO;
use App\Domain\Entity\PortfolioAlbum;
use App\Domain\Entity\PortfolioItem;
use App\Domain\Port\PortfolioAlbumRepositoryInterface;
use App\Domain\Port\PortfolioItemRepositoryInterface;

final class PortfolioService
{
    public function __construct(
        private readonly PortfolioItemRepositoryInterface $itemRepo,
        private readonly PortfolioAlbumRepositoryInterface $albumRepo,
    ) {
    }

    // ── Albums ──────────────────────────────────────────

    /** @return PortfolioAlbumDTO[] */
    public function listAlbums(): array
    {
        $albums = $this->albumRepo->findAllOrdered();

        return array_map(function (PortfolioAlbum $album) {
            $count = count($this->itemRepo->findByAlbum($album->getId()));
            return PortfolioAlbumDTO::fromEntity($album, $count);
        }, $albums);
    }

    public function createAlbum(string $name, ?string $description, ?string $coverImageUrl, int $sortOrder): PortfolioAlbumDTO
    {
        $album = new PortfolioAlbum($name);
        $album->setDescription($description);
        $album->setCoverImageUrl($coverImageUrl);
        $album->setSortOrder($sortOrder);
        $this->albumRepo->save($album);

        return PortfolioAlbumDTO::fromEntity($album);
    }

    public function updateAlbum(int $id, string $name, ?string $description, ?string $coverImageUrl, int $sortOrder): PortfolioAlbumDTO
    {
        $album = $this->albumRepo->findById($id);
        if ($album === null) {
            throw new \DomainException('Album introuvable.');
        }

        $album->setName($name);
        $album->setDescription($description);
        $album->setCoverImageUrl($coverImageUrl);
        $album->setSortOrder($sortOrder);
        $this->albumRepo->save($album);

        $count = count($this->itemRepo->findByAlbum($album->getId()));
        return PortfolioAlbumDTO::fromEntity($album, $count);
    }

    public function deleteAlbum(int $id): void
    {
        $album = $this->albumRepo->findById($id);
        if ($album === null) {
            throw new \DomainException('Album introuvable.');
        }

        $this->albumRepo->remove($album);
    }

    // ── Items ───────────────────────────────────────────

    /** @return PortfolioItemDTO[] */
    public function listAll(): array
    {
        return array_map(
            fn(PortfolioItem $p) => PortfolioItemDTO::fromEntity($p),
            $this->itemRepo->findAllOrdered()
        );
    }

    /** @return PortfolioItemDTO[] */
    public function listByAlbum(int $albumId): array
    {
        return array_map(
            fn(PortfolioItem $p) => PortfolioItemDTO::fromEntity($p),
            $this->itemRepo->findByAlbum($albumId)
        );
    }

    public function create(string $title, string $imageUrl, ?string $description, int $sortOrder, ?int $albumId, bool $isFeatured): PortfolioItemDTO
    {
        $item = new PortfolioItem($title, $imageUrl);
        $item->setDescription($description);
        $item->setSortOrder($sortOrder);
        $item->setIsFeatured($isFeatured);

        if ($albumId !== null) {
            $album = $this->albumRepo->findById($albumId);
            if ($album === null) {
                throw new \DomainException('Album introuvable.');
            }
            $item->setAlbum($album);
        }

        $this->itemRepo->save($item);
        return PortfolioItemDTO::fromEntity($item);
    }

    public function update(int $id, array $data): PortfolioItemDTO
    {
        $item = $this->itemRepo->findById($id);
        if ($item === null) {
            throw new \DomainException('Item portfolio introuvable.');
        }

        if (array_key_exists('title', $data)) {
            $item->setTitle($data['title']);
        }
        if (array_key_exists('description', $data)) {
            $item->setDescription($data['description']);
        }
        if (array_key_exists('imageUrl', $data)) {
            $item->setImageUrl($data['imageUrl']);
        }
        if (array_key_exists('sortOrder', $data)) {
            $item->setSortOrder((int) $data['sortOrder']);
        }
        if (array_key_exists('isFeatured', $data)) {
            $item->setIsFeatured((bool) $data['isFeatured']);
        }
        if (array_key_exists('albumId', $data)) {
            $albumId = $data['albumId'];
            if ($albumId === null) {
                $item->setAlbum(null);
            } else {
                $album = $this->albumRepo->findById((int) $albumId);
                if ($album === null) {
                    throw new \DomainException('Album introuvable.');
                }
                $item->setAlbum($album);
            }
        }

        $this->itemRepo->save($item);
        return PortfolioItemDTO::fromEntity($item);
    }

    public function delete(int $id): void
    {
        $item = $this->itemRepo->findById($id);
        if ($item === null) {
            throw new \DomainException('Item portfolio introuvable.');
        }

        $this->itemRepo->remove($item);
    }
}
