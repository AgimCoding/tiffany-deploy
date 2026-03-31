<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Doctrine\Repository;

use App\Domain\Entity\Appointment;
use App\Domain\Port\AppointmentRepositoryInterface;
use Doctrine\ORM\EntityManagerInterface;

final class DoctrineAppointmentRepository implements AppointmentRepositoryInterface
{
    public function __construct(private readonly EntityManagerInterface $em)
    {
    }

    public function findById(int $id): ?Appointment
    {
        return $this->em->find(Appointment::class, $id);
    }

    public function findByUser(int $userId): array
    {
        return $this->em->getRepository(Appointment::class)->findBy(
            ['user' => $userId],
            ['date' => 'DESC']
        );
    }

    public function findByDate(\DateTimeImmutable $date): array
    {
        return $this->em->createQueryBuilder()
            ->select('a')
            ->from(Appointment::class, 'a')
            ->where('a.date = :date')
            ->setParameter('date', $date->format('Y-m-d'))
            ->getQuery()
            ->getResult();
    }

    public function findAll(): array
    {
        return $this->em->getRepository(Appointment::class)->findBy([], ['createdAt' => 'DESC']);
    }

    public function findByDateRange(\DateTimeImmutable $from, \DateTimeImmutable $to): array
    {
        return $this->em->createQueryBuilder()
            ->select('a')
            ->from(Appointment::class, 'a')
            ->where('a.date >= :from')
            ->andWhere('a.date <= :to')
            ->setParameter('from', $from->format('Y-m-d'))
            ->setParameter('to', $to->format('Y-m-d'))
            ->orderBy('a.date', 'ASC')
            ->addOrderBy('a.timeSlot', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function save(Appointment $appointment): void
    {
        $this->em->persist($appointment);
        $this->em->flush();
    }

    public function remove(Appointment $appointment): void
    {
        $this->em->remove($appointment);
        $this->em->flush();
    }
}
