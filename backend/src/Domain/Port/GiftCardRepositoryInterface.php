<?php

declare(strict_types=1);

namespace App\Domain\Port;

use App\Domain\Entity\GiftCard;

interface GiftCardRepositoryInterface
{
    public function findById(int $id): ?GiftCard;
    public function findByCode(string $code): ?GiftCard;
    /** @return GiftCard[] */
    public function findAll(): array;
    /** @return GiftCard[] */
    public function findByPurchaser(int $userId): array;
    public function save(GiftCard $giftCard): void;
    public function delete(GiftCard $giftCard): void;
}
