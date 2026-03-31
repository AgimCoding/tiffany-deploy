<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Doctrine\Repository;

use App\Domain\Entity\GiftCard;
use App\Domain\Port\GiftCardRepositoryInterface;
use Doctrine\ORM\EntityManagerInterface;

final class DoctrineGiftCardRepository implements GiftCardRepositoryInterface
{
    public function __construct(private readonly EntityManagerInterface $em) {}

    public function findById(int $id): ?GiftCard
    {
        return $this->em->find(GiftCard::class, $id);
    }

    public function findByCode(string $code): ?GiftCard
    {
        return $this->em->getRepository(GiftCard::class)->findOneBy(['code' => strtoupper($code)]);
    }

    public function findAll(): array
    {
        return $this->em->getRepository(GiftCard::class)->findBy([], ['createdAt' => 'DESC']);
    }

    public function findByPurchaser(int $userId): array
    {
        return $this->em->getRepository(GiftCard::class)->findBy(['purchasedBy' => $userId], ['createdAt' => 'DESC']);
    }

    public function save(GiftCard $giftCard): void
    {
        $this->em->persist($giftCard);
        $this->em->flush();
    }

    public function delete(GiftCard $giftCard): void
    {
        $this->em->remove($giftCard);
        $this->em->flush();
    }
}
