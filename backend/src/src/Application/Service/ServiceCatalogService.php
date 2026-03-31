<?php

declare(strict_types=1);

namespace App\Application\Service;

use App\Application\DTO\ServiceDTO;
use App\Domain\Entity\Service;
use App\Domain\Port\ServiceRepositoryInterface;

final class ServiceCatalogService
{
    public function __construct(
        private readonly ServiceRepositoryInterface $serviceRepository,
    ) {
    }

    /** @return ServiceDTO[] */
    public function listAll(): array
    {
        return array_map(
            fn(Service $s) => ServiceDTO::fromEntity($s),
            $this->serviceRepository->findAll()
        );
    }

    public function create(
        string $name,
        string $price,
        int $durationMin,
        string $description,
        bool $isQuote,
        int $sortOrder,
        ?string $category = null
    ): ServiceDTO {
        $service = new Service($name, $price, $durationMin, $description, $isQuote);
        $service->setSortOrder($sortOrder);
        $service->setCategory($category);
        $this->serviceRepository->save($service);

        return ServiceDTO::fromEntity($service);
    }

    public function update(int $id, array $data): ServiceDTO
    {
        $service = $this->serviceRepository->findById($id);
        if ($service === null) {
            throw new \DomainException('Service introuvable.');
        }

        if (isset($data['name'])) $service->setName($data['name']);
        if (isset($data['price'])) $service->setPrice($data['price']);
        if (isset($data['durationMin'])) $service->setDurationMin($data['durationMin']);
        if (isset($data['description'])) $service->setDescription($data['description']);
        if (isset($data['isQuote'])) $service->setIsQuote($data['isQuote']);
        if (isset($data['sortOrder'])) $service->setSortOrder($data['sortOrder']);
        if (array_key_exists('category', $data)) $service->setCategory($data['category']);

        $this->serviceRepository->save($service);

        return ServiceDTO::fromEntity($service);
    }

    public function delete(int $id): void
    {
        $service = $this->serviceRepository->findById($id);
        if ($service === null) {
            throw new \DomainException('Service introuvable.');
        }

        $this->serviceRepository->remove($service);
    }
}
