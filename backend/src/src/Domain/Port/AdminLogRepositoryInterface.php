<?php

declare(strict_types=1);

namespace App\Domain\Port;

use App\Domain\Entity\AdminLog;

interface AdminLogRepositoryInterface
{
    /** @return AdminLog[] */
    public function findRecent(int $limit = 100): array;

    /** @return AdminLog[] */
    public function findByDateRange(\DateTimeImmutable $from, \DateTimeImmutable $to): array;

    public function save(AdminLog $log): void;

    public function deleteOlderThan(\DateTimeImmutable $before): int;
}
