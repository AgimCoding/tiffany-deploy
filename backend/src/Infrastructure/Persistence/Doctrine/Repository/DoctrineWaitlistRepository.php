<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Doctrine\Repository;

use App\Domain\Entity\Waitlist;
use App\Domain\Port\WaitlistRepositoryInterface;
use Doctrine\ORM\EntityManagerInterface;

final class DoctrineWaitlistRepository implements WaitlistRepositoryInterface
{
    public function __construct(private readonly EntityManagerInterface $em) {}

    public function findByService(int $serviceId): array
    {
        return $this->em->getRepository(Waitlist::class)->findBy(['service' => $serviceId], ['createdAt' => 'ASC']);
    }

    public function findPendingByService(int $serviceId): array
    {
        return $this->em->getRepository(Waitlist::class)->findBy(
            ['service' => $serviceId, 'notified' => false],
            ['createdAt' => 'ASC']
        );
    }

    public function findByUser(int $userId): array
    {
        return $this->em->getRepository(Waitlist::class)->findBy(['user' => $userId], ['createdAt' => 'DESC']);
    }

    public function findById(int $id): ?Waitlist
    {
        return $this->em->find(Waitlist::class, $id);
    }

    public function save(Waitlist $entry): void
    {
        $this->em->persist($entry);
        $this->em->flush();
    }

    public function delete(Waitlist $entry): void
    {
        $this->em->remove($entry);
        $this->em->flush();
    }
}
