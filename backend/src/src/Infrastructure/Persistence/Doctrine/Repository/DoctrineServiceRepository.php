<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Doctrine\Repository;

use App\Domain\Entity\Service;
use App\Domain\Port\ServiceRepositoryInterface;
use Doctrine\ORM\EntityManagerInterface;

final class DoctrineServiceRepository implements ServiceRepositoryInterface
{
    public function __construct(private readonly EntityManagerInterface $em)
    {
    }

    public function findById(int $id): ?Service
    {
        return $this->em->find(Service::class, $id);
    }

    public function findAll(): array
    {
        return $this->em->getRepository(Service::class)->findBy([], ['sortOrder' => 'ASC']);
    }

    public function save(Service $service): void
    {
        $this->em->persist($service);
        $this->em->flush();
    }

    public function remove(Service $service): void
    {
        $this->em->remove($service);
        $this->em->flush();
    }
}
