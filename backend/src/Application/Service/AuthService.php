<?php

declare(strict_types=1);

namespace App\Application\Service;

use App\Application\DTO\UserDTO;
use App\Domain\Entity\User;
use App\Domain\Port\UserRepositoryInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

final class AuthService
{
    public function __construct(
        private readonly UserRepositoryInterface $userRepository,
        private readonly UserPasswordHasherInterface $passwordHasher,
    ) {
    }

    public function register(string $fullName, string $email, string $phone, string $plainPassword): UserDTO
    {
        $existing = $this->userRepository->findByEmail($email);
        if ($existing !== null) {
            throw new \DomainException('Un compte avec cet email existe déjà.');
        }

        $user = new User($fullName, $email, '');
        $user->setPhone($phone);
        $hashedPassword = $this->passwordHasher->hashPassword($user, $plainPassword);
        $user->setPassword($hashedPassword);

        $this->userRepository->save($user);

        return UserDTO::fromEntity($user);
    }

    public function getProfile(User $user): UserDTO
    {
        return UserDTO::fromEntity($user);
    }

    public function saveUser(User $user): void
    {
        $this->userRepository->save($user);
    }
}
