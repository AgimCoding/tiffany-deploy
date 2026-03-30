<?php

declare(strict_types=1);

namespace App\Domain\Port;

use App\Domain\Entity\Service;

interface ServiceRepositoryInterface
{
    public function findById(int $id): ?Service;

    /** @return Service[] */
    public function findAll(): array;

    public function save(Service $service): void;

    public function remove(Service $service): void;
}
