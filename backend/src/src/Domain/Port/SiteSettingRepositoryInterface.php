<?php
declare(strict_types=1);
namespace App\Domain\Port;

use App\Domain\Entity\SiteSetting;

interface SiteSettingRepositoryInterface
{
    public function findByKey(string $key): ?SiteSetting;
    /** @return SiteSetting[] */
    public function findByCategory(string $category): array;
    /** @return SiteSetting[] */
    public function findAll(): array;
    public function save(SiteSetting $setting): void;
}
