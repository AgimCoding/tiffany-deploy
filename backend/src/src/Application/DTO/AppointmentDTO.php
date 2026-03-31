<?php

declare(strict_types=1);

namespace App\Application\DTO;

use App\Domain\Entity\Appointment;

final class AppointmentDTO
{
    public function __construct(
        public readonly int $id,
        public readonly int $serviceId,
        public readonly string $serviceName,
        public readonly string $servicePrice,
        public readonly int $serviceDurationMin,
        public readonly string $date,
        public readonly string $timeSlot,
        public readonly ?string $alternativeDate,
        public readonly string $status,
        public readonly ?string $comment,
        public readonly ?string $finalPrice,
        public readonly ?int $familyMemberId,
        public readonly ?string $familyMemberName,
        public readonly int $clientId,
        public readonly string $clientName,
        public readonly string $clientEmail,
        public readonly ?string $clientPhone,
        public readonly string $createdAt,
    ) {
    }

    public static function fromEntity(Appointment $appointment): self
    {
        return new self(
            id: $appointment->getId(),
            serviceId: $appointment->getService()->getId(),
            serviceName: $appointment->getService()->getName(),
            servicePrice: $appointment->getService()->getPrice(),
            serviceDurationMin: $appointment->getService()->getDurationMin(),
            date: $appointment->getDate()->format('Y-m-d'),
            timeSlot: $appointment->getTimeSlot(),
            alternativeDate: $appointment->getAlternativeDate()?->format('Y-m-d'),
            status: $appointment->getStatus(),
            comment: $appointment->getComment(),
            finalPrice: $appointment->getFinalPrice(),
            familyMemberId: $appointment->getFamilyMember()?->getId(),
            familyMemberName: $appointment->getFamilyMember()?->getFullName(),
            clientId: $appointment->getUser()->getId(),
            clientName: $appointment->getUser()->getFullName(),
            clientEmail: $appointment->getUser()->getEmail(),
            clientPhone: $appointment->getUser()->getPhone(),
            createdAt: $appointment->getCreatedAt()->format('c'),
        );
    }
}
