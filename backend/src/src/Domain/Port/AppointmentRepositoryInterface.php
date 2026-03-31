<?php

declare(strict_types=1);

namespace App\Domain\Port;

use App\Domain\Entity\Appointment;

interface AppointmentRepositoryInterface
{
    public function findById(int $id): ?Appointment;

    /** @return Appointment[] */
    public function findByUser(int $userId): array;

    /** @return Appointment[] */
    public function findByDate(\DateTimeImmutable $date): array;

    /** @return Appointment[] */
    public function findAll(): array;

    /** @return Appointment[] */
    public function findByDateRange(\DateTimeImmutable $from, \DateTimeImmutable $to): array;

    public function save(Appointment $appointment): void;

    public function remove(Appointment $appointment): void;
}
