<?php

declare(strict_types=1);

namespace App\Application\DTO;

use App\Domain\Entity\User;

final class UserDTO
{
    public function __construct(
        public readonly int $id,
        public readonly string $fullName,
        public readonly string $email,
        public readonly ?string $phone,
        public readonly array $roles,
        public readonly string $createdAt,
    ) {
    }

    public static function fromEntity(User $user): self
    {
        return new self(
            id: $user->getId(),
            fullName: $user->getFullName(),
            email: $user->getEmail(),
            phone: $user->getPhone(),
            roles: $user->getRoles(),
            createdAt: $user->getCreatedAt()->format('c'),
        );
    }
}
