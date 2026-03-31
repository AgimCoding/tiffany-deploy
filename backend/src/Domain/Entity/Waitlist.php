<?php

declare(strict_types=1);

namespace App\Domain\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'waitlist')]
#[ORM\Index(columns: ['service_id', 'notified'], name: 'idx_waitlist_service')]
class Waitlist
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private User $user;

    #[ORM\ManyToOne(targetEntity: Service::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Service $service;

    #[ORM\Column(type: 'date_immutable', nullable: true)]
    private ?\DateTimeImmutable $preferredDate = null;

    #[ORM\Column(type: 'boolean')]
    private bool $notified = false;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    public function __construct(User $user, Service $service, ?\DateTimeImmutable $preferredDate = null)
    {
        $this->user = $user;
        $this->service = $service;
        $this->preferredDate = $preferredDate;
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int { return $this->id; }
    public function getUser(): User { return $this->user; }
    public function getService(): Service { return $this->service; }
    public function getPreferredDate(): ?\DateTimeImmutable { return $this->preferredDate; }
    public function isNotified(): bool { return $this->notified; }
    public function getCreatedAt(): \DateTimeImmutable { return $this->createdAt; }

    public function markNotified(): void
    {
        $this->notified = true;
    }
}
