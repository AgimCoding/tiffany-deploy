<?php

declare(strict_types=1);

namespace App\Domain\Entity;

use App\Domain\ValueObject\AppointmentStatus;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'appointments')]
class Appointment
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: User::class, inversedBy: 'appointments')]
    #[ORM\JoinColumn(nullable: false)]
    private User $user;

    #[ORM\ManyToOne(targetEntity: Service::class)]
    #[ORM\JoinColumn(nullable: false)]
    private Service $service;

    #[ORM\Column(type: 'date_immutable')]
    private \DateTimeImmutable $date;

    #[ORM\Column(type: 'string', length: 5)]
    private string $timeSlot;

    #[ORM\Column(type: 'date_immutable', nullable: true)]
    private ?\DateTimeImmutable $alternativeDate = null;

    #[ORM\Column(type: 'string', length: 30)]
    private string $status;

    #[ORM\ManyToOne(targetEntity: FamilyMember::class)]
    #[ORM\JoinColumn(name: 'family_member_id', nullable: true, onDelete: 'SET NULL')]
    private ?FamilyMember $familyMember = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $comment = null;

    #[ORM\Column(type: 'decimal', precision: 10, scale: 2, nullable: true)]
    private ?string $finalPrice = null;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    public function __construct(
        User $user,
        Service $service,
        \DateTimeInterface $date,
        string $timeSlot
    ) {
        $this->user = $user;
        $this->service = $service;
        $this->date = $date;
        $this->timeSlot = $timeSlot;
        $this->status = AppointmentStatus::PENDING->value;
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getUser(): User
    {
        return $this->user;
    }

    public function getService(): Service
    {
        return $this->service;
    }

    public function getDate(): \DateTimeImmutable
    {
        return $this->date;
    }

    public function setDate(\DateTimeImmutable $date): void
    {
        $this->date = $date;
    }

    public function getTimeSlot(): string
    {
        return $this->timeSlot;
    }

    public function setTimeSlot(string $timeSlot): void
    {
        $this->timeSlot = $timeSlot;
    }

    public function getAlternativeDate(): ?\DateTimeImmutable
    {
        return $this->alternativeDate;
    }

    public function setAlternativeDate(?\DateTimeImmutable $alternativeDate): void
    {
        $this->alternativeDate = $alternativeDate;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function confirm(): void
    {
        $this->status = AppointmentStatus::CONFIRMED->value;
    }

    public function complete(string $finalPrice): void
    {
        $this->status = AppointmentStatus::COMPLETED->value;
        $this->finalPrice = $finalPrice;
    }

    public function cancel(): void
    {
        $this->status = AppointmentStatus::CANCELLED->value;
    }

    public function getComment(): ?string
    {
        return $this->comment;
    }

    public function setComment(?string $comment): void
    {
        $this->comment = $comment;
    }

    public function getFinalPrice(): ?string
    {
        return $this->finalPrice;
    }

    public function getFamilyMember(): ?FamilyMember
    {
        return $this->familyMember;
    }

    public function setFamilyMember(?FamilyMember $familyMember): void
    {
        $this->familyMember = $familyMember;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }
}
