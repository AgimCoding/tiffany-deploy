<?php

declare(strict_types=1);

namespace App\Application\DTO;

use App\Domain\Entity\Service;

final class ServiceDTO
{
    public function __construct(
        public readonly int $id,
        public readonly string $name,
        public readonly string $price,
        public readonly int $durationMin,
        public readonly string $description,
        public readonly bool $isQuote,
        public readonly int $sortOrder,
        public readonly ?string $category = null,
    ) {
    }

    public static function fromEntity(Service $service): self
    {
        return new self(
            id: $service->getId(),
            name: $service->getName(),
            price: $service->getPrice(),
            durationMin: $service->getDurationMin(),
            description: $service->getDescription(),
            isQuote: $service->isQuote(),
            sortOrder: $service->getSortOrder(),
            category: $service->getCategory(),
        );
    }
}
