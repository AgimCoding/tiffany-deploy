<?php

declare(strict_types=1);

namespace App\Application\DTO;

use App\Domain\Entity\FamilyMember;

final class FamilyMemberDTO
{
    public function __construct(
        public readonly int $id,
        public readonly string $firstName,
        public readonly string $lastName,
        public readonly ?string $relationship,
    ) {
    }

    public static function fromEntity(FamilyMember $member): self
    {
        return new self(
            id: $member->getId(),
            firstName: $member->getFirstName(),
            lastName: $member->getLastName(),
            relationship: $member->getRelationship(),
        );
    }
}
