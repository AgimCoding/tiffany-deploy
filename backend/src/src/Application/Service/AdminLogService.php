<?php

declare(strict_types=1);

namespace App\Application\Service;

use App\Domain\Entity\AdminLog;
use App\Domain\Entity\User;
use App\Domain\Port\AdminLogRepositoryInterface;

final class AdminLogService
{
    public function __construct(
        private readonly AdminLogRepositoryInterface $repository
    ) {
    }

    public function log(User $admin, string $action, string $entityType, ?int $entityId = null, ?string $details = null): void
    {
        $log = new AdminLog($admin, $action, $entityType, $entityId, $details);
        $this->repository->save($log);
    }

    public function getRecent(int $limit = 100): array
    {
        return array_map(fn(AdminLog $l) => [
            'id' => $l->getId(),
            'adminName' => $l->getAdmin()->getFullName(),
            'action' => $l->getAction(),
            'entityType' => $l->getEntityType(),
            'entityId' => $l->getEntityId(),
            'details' => $l->getDetails(),
            'createdAt' => $l->getCreatedAt()->format('c'),
        ], $this->repository->findRecent($limit));
    }

    public function cleanup(int $daysToKeep = 90): int
    {
        $before = new \DateTimeImmutable("-{$daysToKeep} days");
        return $this->repository->deleteOlderThan($before);
    }
}
