<?php

declare(strict_types=1);

namespace App\Domain\Port;

use App\Domain\Entity\Waitlist;

interface WaitlistRepositoryInterface
{
    /** @return Waitlist[] */
    public function findByService(int $serviceId): array;

    /** @return Waitlist[] */
    public function findPendingByService(int $serviceId): array;

    /** @return Waitlist[] */
    public function findByUser(int $userId): array;

    public function findById(int $id): ?Waitlist;

    public function save(Waitlist $entry): void;

    public function delete(Waitlist $entry): void;
}
