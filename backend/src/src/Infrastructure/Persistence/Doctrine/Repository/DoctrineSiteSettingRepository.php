<?php
declare(strict_types=1);
namespace App\Infrastructure\Persistence\Doctrine\Repository;

use App\Domain\Entity\SiteSetting;
use App\Domain\Port\SiteSettingRepositoryInterface;
use Doctrine\ORM\EntityManagerInterface;

final class DoctrineSiteSettingRepository implements SiteSettingRepositoryInterface
{
    public function __construct(private readonly EntityManagerInterface $em) {}

    public function findByKey(string $key): ?SiteSetting
    {
        return $this->em->find(SiteSetting::class, $key);
    }

    public function findByCategory(string $category): array
    {
        return $this->em->getRepository(SiteSetting::class)->findBy(['category' => $category]);
    }

    public function findAll(): array
    {
        return $this->em->getRepository(SiteSetting::class)->findBy([], ['category' => 'ASC', 'settingKey' => 'ASC']);
    }

    public function save(SiteSetting $setting): void
    {
        $this->em->persist($setting);
        $this->em->flush();
    }
}
