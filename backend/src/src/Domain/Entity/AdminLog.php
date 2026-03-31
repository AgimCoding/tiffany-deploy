<?php

declare(strict_types=1);

namespace App\Domain\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'admin_logs')]
#[ORM\Index(columns: ['created_at'], name: 'idx_admin_log_date')]
class AdminLog
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false)]
    private User $admin;

    #[ORM\Column(type: 'string', length: 50)]
    private string $action;

    #[ORM\Column(type: 'string', length: 50)]
    private string $entityType;

    #[ORM\Column(type: 'integer', nullable: true)]
    private ?int $entityId;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $details;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    public function __construct(
        User $admin,
        string $action,
        string $entityType,
        ?int $entityId = null,
        ?string $details = null
    ) {
        $this->admin = $admin;
        $this->action = $action;
        $this->entityType = $entityType;
        $this->entityId = $entityId;
        $this->details = $details;
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int { return $this->id; }
    public function getAdmin(): User { return $this->admin; }
    public function getAction(): string { return $this->action; }
    public function getEntityType(): string { return $this->entityType; }
    public function getEntityId(): ?int { return $this->entityId; }
    public function getDetails(): ?string { return $this->details; }
    public function getCreatedAt(): \DateTimeImmutable { return $this->createdAt; }
}
