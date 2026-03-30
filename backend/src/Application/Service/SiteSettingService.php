<?php
declare(strict_types=1);
namespace App\Application\Service;

use App\Domain\Entity\SiteSetting;
use App\Domain\Port\SiteSettingRepositoryInterface;

final class SiteSettingService
{
    public function __construct(private readonly SiteSettingRepositoryInterface $repo) {}

    public function getAll(): array
    {
        $settings = $this->repo->findAll();
        $result = [];
        foreach ($settings as $s) {
            $result[$s->getSettingKey()] = $s->getValue();
        }
        return $result;
    }

    public function getByCategory(string $category): array
    {
        $settings = $this->repo->findByCategory($category);
        $result = [];
        foreach ($settings as $s) {
            $result[$s->getSettingKey()] = $s->getValue();
        }
        return $result;
    }

    public function get(string $key): ?string
    {
        $setting = $this->repo->findByKey($key);
        return $setting?->getValue();
    }

    public function set(string $key, string $value, string $category = 'general'): void
    {
        $setting = $this->repo->findByKey($key);
        if ($setting === null) {
            $setting = new SiteSetting($key, $value, $category);
        } else {
            $setting->setValue($value);
        }
        $this->repo->save($setting);
    }

    public function bulkUpdate(array $data): void
    {
        foreach ($data as $key => $value) {
            if (is_array($value)) {
                $this->set($key, $value['value'], $value['category'] ?? 'general');
            } else {
                $this->set($key, $value);
            }
        }
    }
}
