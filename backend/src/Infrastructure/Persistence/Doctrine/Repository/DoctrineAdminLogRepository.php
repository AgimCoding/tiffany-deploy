<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Doctrine\Repository;

use App\Domain\Entity\AdminLog;
use App\Domain\Port\AdminLogRepositoryInterface;
use Doctrine\ORM\EntityManagerInterface;

final class DoctrineAdminLogRepository implements AdminLogRepositoryInterface
{
    public function __construct(private readonly EntityManagerInterface $em)
    {
    }

    public function findRecent(int $limit = 100): array
    {
        return $this->em->createQueryBuilder()
            ->select('l')
            ->from(AdminLog::class, 'l')
            ->orderBy('l.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    public function findByDateRange(\DateTimeImmutable $from, \DateTimeImmutable $to): array
    {
        return $this->em->createQueryBuilder()
            ->select('l')
            ->from(AdminLog::class, 'l')
            ->where('l.createdAt >= :from')
            ->andWhere('l.createdAt <= :to')
            ->setParameter('from', $from)
            ->setParameter('to', $to)
            ->orderBy('l.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function save(AdminLog $log): void
    {
        $this->em->persist($log);
        $this->em->flush();
    }

    public function deleteOlderThan(\DateTimeImmutable $before): int
    {
        return $this->em->createQueryBuilder()
            ->delete(AdminLog::class, 'l')
            ->where('l.createdAt < :before')
            ->setParameter('before', $before)
            ->getQuery()
            ->execute();
    }
}
