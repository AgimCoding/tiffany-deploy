<?php

declare(strict_types=1);

namespace App\Domain\Port;

use App\Domain\Entity\FamilyMember;

interface FamilyMemberRepositoryInterface
{
    public function findById(int $id): ?FamilyMember;

    /** @return FamilyMember[] */
    public function findByOwner(int $userId): array;

    public function save(FamilyMember $member): void;

    public function remove(FamilyMember $member): void;
}
