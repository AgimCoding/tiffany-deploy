<?php

declare(strict_types=1);

namespace App\Application\Service;

use App\Application\DTO\FamilyMemberDTO;
use App\Domain\Entity\FamilyMember;
use App\Domain\Entity\User;
use App\Domain\Port\FamilyMemberRepositoryInterface;

final class FamilyMemberService
{
    public function __construct(
        private readonly FamilyMemberRepositoryInterface $repo,
    ) {
    }

    /** @return FamilyMemberDTO[] */
    public function listForUser(User $user): array
    {
        return array_map(
            fn(FamilyMember $m) => FamilyMemberDTO::fromEntity($m),
            $this->repo->findByOwner($user->getId())
        );
    }

    public function create(User $owner, string $firstName, string $lastName, ?string $relationship): FamilyMemberDTO
    {
        $member = new FamilyMember($owner, $firstName, $lastName);
        $member->setRelationship($relationship);
        $this->repo->save($member);

        return FamilyMemberDTO::fromEntity($member);
    }

    public function update(int $id, User $owner, array $data): FamilyMemberDTO
    {
        $member = $this->repo->findById($id);
        if ($member === null || $member->getOwner()->getId() !== $owner->getId()) {
            throw new \DomainException('Membre introuvable.');
        }

        if (isset($data['firstName'])) $member->setFirstName($data['firstName']);
        if (isset($data['lastName'])) $member->setLastName($data['lastName']);
        if (array_key_exists('relationship', $data)) $member->setRelationship($data['relationship']);

        $this->repo->save($member);
        return FamilyMemberDTO::fromEntity($member);
    }

    public function delete(int $id, User $owner): void
    {
        $member = $this->repo->findById($id);
        if ($member === null || $member->getOwner()->getId() !== $owner->getId()) {
            throw new \DomainException('Membre introuvable.');
        }

        $this->repo->remove($member);
    }
}
