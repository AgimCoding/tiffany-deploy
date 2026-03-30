<?php

declare(strict_types=1);

namespace App\Application\Service;

use App\Application\DTO\AppointmentDTO;
use App\Domain\Entity\Appointment;
use App\Domain\Entity\User;
use App\Domain\Port\AppointmentRepositoryInterface;
use App\Domain\Port\FamilyMemberRepositoryInterface;
use App\Domain\Port\ServiceRepositoryInterface;

final class AppointmentService
{
    public function __construct(
        private readonly AppointmentRepositoryInterface $appointmentRepository,
        private readonly ServiceRepositoryInterface $serviceRepository,
        private readonly FamilyMemberRepositoryInterface $familyMemberRepository,
    ) {
    }

    public function book(
        User $user,
        int $serviceId,
        string $date,
        string $timeSlot,
        ?string $alternativeDate = null,
        ?string $comment = null,
        ?int $familyMemberId = null,
    ): AppointmentDTO {
        $service = $this->serviceRepository->findById($serviceId);
        if ($service === null) {
            throw new \DomainException('Prestation introuvable.');
        }

        $appointment = new Appointment(
            $user,
            $service,
            new \DateTimeImmutable($date),
            $timeSlot,
        );

        if ($alternativeDate) {
            $appointment->setAlternativeDate(new \DateTimeImmutable($alternativeDate));
        }

        if ($comment) {
            $appointment->setComment($comment);
        }

        if ($familyMemberId) {
            $member = $this->familyMemberRepository->findById($familyMemberId);
            if ($member !== null && $member->getOwner()->getId() === $user->getId()) {
                $appointment->setFamilyMember($member);
            }
        }

        // Auto-confirm since slots are verified as free by the frontend
        $appointment->confirm();

        $this->appointmentRepository->save($appointment);

        return AppointmentDTO::fromEntity($appointment);
    }

    /**
     * Returns all 15-min slots occupied by appointments on a given date.
     * @return string[]
     */
    public function getBookedSlots(string $date): array
    {
        $appointments = $this->appointmentRepository->findByDate(new \DateTimeImmutable($date));
        $bookedSlots = [];

        foreach ($appointments as $appointment) {
            if ($appointment->getStatus() === 'cancelled') {
                continue;
            }

            $startTime = $appointment->getTimeSlot(); // e.g. "09:00"
            $duration = $appointment->getService()->getDurationMin();
            $slotsNeeded = (int) ceil($duration / 15);

            [$h, $m] = explode(':', $startTime);
            $startMinutes = (int) $h * 60 + (int) $m;

            for ($i = 0; $i < $slotsNeeded; $i++) {
                $totalMin = $startMinutes + ($i * 15);
                $slotH = str_pad((string) intdiv($totalMin, 60), 2, '0', STR_PAD_LEFT);
                $slotM = str_pad((string) ($totalMin % 60), 2, '0', STR_PAD_LEFT);
                $bookedSlots[] = $slotH . ':' . $slotM;
            }
        }

        return array_values(array_unique($bookedSlots));
    }

    public function findById(int $id): ?AppointmentDTO
    {
        $a = $this->appointmentRepository->find($id);
        return $a ? AppointmentDTO::fromEntity($a) : null;
    }

    /** @return AppointmentDTO[] */
    public function listForUser(User $user): array
    {
        $appointments = $this->appointmentRepository->findByUser($user->getId());

        return array_map(
            fn(Appointment $a) => AppointmentDTO::fromEntity($a),
            $appointments
        );
    }

    /** @return AppointmentDTO[] */
    public function listAll(): array
    {
        return array_map(
            fn(Appointment $a) => AppointmentDTO::fromEntity($a),
            $this->appointmentRepository->findAll()
        );
    }

    /** @return AppointmentDTO[] */
    public function listByDate(string $date): array
    {
        $appointments = $this->appointmentRepository->findByDate(new \DateTimeImmutable($date));
        usort($appointments, fn(Appointment $a, Appointment $b) => $a->getTimeSlot() <=> $b->getTimeSlot());

        return array_map(
            fn(Appointment $a) => AppointmentDTO::fromEntity($a),
            $appointments
        );
    }

    /** @return AppointmentDTO[] */
    public function listByDateRange(string $from, string $to): array
    {
        $appointments = $this->appointmentRepository->findByDateRange(
            new \DateTimeImmutable($from),
            new \DateTimeImmutable($to),
        );

        return array_map(
            fn(Appointment $a) => AppointmentDTO::fromEntity($a),
            $appointments
        );
    }

    public function confirm(int $id): AppointmentDTO
    {
        $appointment = $this->appointmentRepository->findById($id);
        if ($appointment === null) {
            throw new \DomainException('Rendez-vous introuvable.');
        }

        $appointment->confirm();
        $this->appointmentRepository->save($appointment);

        return AppointmentDTO::fromEntity($appointment);
    }

    public function complete(int $id, string $finalPrice): AppointmentDTO
    {
        $appointment = $this->appointmentRepository->findById($id);
        if ($appointment === null) {
            throw new \DomainException('Rendez-vous introuvable.');
        }

        $appointment->complete($finalPrice);
        $this->appointmentRepository->save($appointment);

        return AppointmentDTO::fromEntity($appointment);
    }

    public function cancel(int $id): AppointmentDTO
    {
        $appointment = $this->appointmentRepository->findById($id);
        if ($appointment === null) {
            throw new \DomainException('Rendez-vous introuvable.');
        }

        $appointment->cancel();
        $this->appointmentRepository->save($appointment);

        return AppointmentDTO::fromEntity($appointment);
    }

    public function reschedule(int $id, string $date, string $timeSlot): AppointmentDTO
    {
        $appointment = $this->appointmentRepository->findById($id);
        if ($appointment === null) {
            throw new \DomainException('Rendez-vous introuvable.');
        }

        $appointment->setDate(new \DateTimeImmutable($date));
        $appointment->setTimeSlot($timeSlot);
        $appointment->confirm();
        $this->appointmentRepository->save($appointment);

        return AppointmentDTO::fromEntity($appointment);
    }

    public function delete(int $id): void
    {
        $appointment = $this->appointmentRepository->findById($id);
        if ($appointment === null) {
            throw new \DomainException('Rendez-vous introuvable.');
        }

        $this->appointmentRepository->remove($appointment);
    }

    /**
     * Find the next available slot for a given service, scanning the next N days.
     * @return array{date: string, timeSlot: string}|null
     */
    public function findNextAvailableSlot(int $serviceId, ?string $weeklyScheduleJson = null, int $maxDays = 14): ?array
    {
        $service = $this->serviceRepository->findById($serviceId);
        if ($service === null) {
            return null;
        }

        $slotsNeeded = (int) ceil($service->getDurationMin() / 15);
        $weekly = null;
        if ($weeklyScheduleJson) {
            try {
                $weekly = json_decode($weeklyScheduleJson, true);
            } catch (\Throwable) {
            }
        }

        $dayMap = ['dimanche', 'lundi', 'mardi', 'mercredi', 'jeudi', 'vendredi', 'samedi'];
        $defaultSlots = ['09:00', '09:15', '09:30', '09:45', '10:00', '10:15', '10:30', '10:45',
                         '11:00', '11:15', '11:30', '11:45', '14:00', '14:15', '14:30', '14:45',
                         '15:00', '15:15', '15:30', '15:45', '16:00', '16:15', '16:30', '16:45',
                         '17:00', '17:15', '17:30', '17:45'];

        $today = new \DateTimeImmutable('today');

        for ($d = 0; $d < $maxDays; $d++) {
            $date = $today->modify("+{$d} days");
            $dayKey = $dayMap[(int) $date->format('w')];

            // Determine available slots for this day
            $daySlots = $defaultSlots;
            if ($weekly !== null) {
                if (!isset($weekly[$dayKey]) || empty($weekly[$dayKey]['active'])) {
                    continue; // salon fermé
                }
                // Expand configured slots to 15-min intervals
                $configSlots = $weekly[$dayKey]['slots'] ?? [];
                $daySlots = [];
                foreach ($configSlots as $slot) {
                    $daySlots[] = $slot;
                    // Also add intermediate 15-min slots
                    [$sh, $sm] = explode(':', $slot);
                    $startMin = (int) $sh * 60 + (int) $sm;
                    for ($i = 1; $i < 4; $i++) {
                        $nextMin = $startMin + ($i * 15);
                        $nextSlot = str_pad((string) intdiv($nextMin, 60), 2, '0', STR_PAD_LEFT) . ':' . str_pad((string) ($nextMin % 60), 2, '0', STR_PAD_LEFT);
                        // Only add if before next hour slot or in configured range
                        if (!in_array($nextSlot, $daySlots)) {
                            $daySlots[] = $nextSlot;
                        }
                    }
                }
                sort($daySlots);
                // Deduplicate
                $daySlots = array_values(array_unique($daySlots));
            }

            if (empty($daySlots)) {
                continue;
            }

            // Skip past times for today
            $now = new \DateTimeImmutable();
            $isToday = $date->format('Y-m-d') === $now->format('Y-m-d');

            // Get booked slots for this date
            $bookedSlots = $this->getBookedSlots($date->format('Y-m-d'));

            // Check each slot
            foreach ($daySlots as $slot) {
                if ($isToday) {
                    $slotTime = new \DateTimeImmutable($date->format('Y-m-d') . ' ' . $slot);
                    if ($slotTime <= $now->modify('+30 minutes')) {
                        continue; // skip slots in the past or too soon
                    }
                }

                // Check if this slot + needed consecutive slots are all free
                $canBook = true;
                for ($i = 0; $i < $slotsNeeded; $i++) {
                    [$ch, $cm] = explode(':', $slot);
                    $totalMin = (int) $ch * 60 + (int) $cm + ($i * 15);
                    $checkSlot = str_pad((string) intdiv($totalMin, 60), 2, '0', STR_PAD_LEFT) . ':' . str_pad((string) ($totalMin % 60), 2, '0', STR_PAD_LEFT);

                    if (in_array($checkSlot, $bookedSlots)) {
                        $canBook = false;
                        break;
                    }
                    // For consecutive slots (i > 0), check they exist in available slots
                    if ($i > 0 && !in_array($checkSlot, $daySlots)) {
                        $canBook = false;
                        break;
                    }
                }

                if ($canBook) {
                    return [
                        'date' => $date->format('Y-m-d'),
                        'timeSlot' => $slot,
                        'dayLabel' => $dayKey,
                    ];
                }
            }
        }

        return null;
    }

    /**
     * Count completed appointments for a user (loyalty).
     */
    public function countCompletedForUser(int $userId): int
    {
        $appointments = $this->appointmentRepository->findByUser($userId);
        $count = 0;
        foreach ($appointments as $a) {
            if ($a->getStatus() === 'completed') {
                $count++;
            }
        }
        return $count;
    }
}
