<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Doctrine\Repository;

use App\Domain\Entity\FamilyMember;
use App\Domain\Port\FamilyMemberRepositoryInterface;
use Doctrine\ORM\EntityManagerInterface;

final class DoctrineFamilyMemberRepository implements FamilyMemberRepositoryInterface
{
    public function __construct(private readonly EntityManagerInterface $em)
    {
    }

    public function findById(int $id): ?FamilyMember
    {
        return $this->em->find(FamilyMember::class, $id);
    }

    public function findByOwner(int $userId): array
    {
        return $this->em->getRepository(FamilyMember::class)->findBy(
            ['owner' => $userId],
            ['firstName' => 'ASC']
        );
    }

    public function save(FamilyMember $member): void
    {
        $this->em->persist($member);
        $this->em->flush();
    }

    public function remove(FamilyMember $member): void
    {
        $this->em->remove($member);
        $this->em->flush();
    }
}
