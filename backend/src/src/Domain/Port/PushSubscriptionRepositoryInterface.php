<?php

declare(strict_types=1);

namespace App\Domain\Port;

use App\Domain\Entity\PushSubscription;

interface PushSubscriptionRepositoryInterface
{
    /** @return PushSubscription[] */
    public function findByUserId(int $userId): array;

    /** @return PushSubscription[] */
    public function findAll(): array;

    public function findByEndpoint(string $endpoint): ?PushSubscription;

    public function save(PushSubscription $subscription): void;

    public function delete(PushSubscription $subscription): void;

    public function deleteByEndpoint(string $endpoint): void;
}
