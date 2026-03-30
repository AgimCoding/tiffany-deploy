<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Doctrine\Repository;

use App\Domain\Entity\PushSubscription;
use App\Domain\Port\PushSubscriptionRepositoryInterface;
use Doctrine\ORM\EntityManagerInterface;

final class DoctrinePushSubscriptionRepository implements PushSubscriptionRepositoryInterface
{
    public function __construct(private readonly EntityManagerInterface $em)
    {
    }

    public function findByUserId(int $userId): array
    {
        return $this->em->getRepository(PushSubscription::class)
            ->findBy(['user' => $userId]);
    }

    public function findAll(): array
    {
        return $this->em->getRepository(PushSubscription::class)->findAll();
    }

    public function findByEndpoint(string $endpoint): ?PushSubscription
    {
        return $this->em->getRepository(PushSubscription::class)
            ->findOneBy(['endpoint' => $endpoint]);
    }

    public function save(PushSubscription $subscription): void
    {
        $this->em->persist($subscription);
        $this->em->flush();
    }

    public function delete(PushSubscription $subscription): void
    {
        $this->em->remove($subscription);
        $this->em->flush();
    }

    public function deleteByEndpoint(string $endpoint): void
    {
        $sub = $this->findByEndpoint($endpoint);
        if ($sub) {
            $this->em->remove($sub);
            $this->em->flush();
        }
    }
}
