<?php

declare(strict_types=1);

namespace App\Domain\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'push_subscriptions')]
#[ORM\Index(columns: ['user_id'], name: 'idx_push_user')]
#[ORM\UniqueConstraint(columns: ['endpoint'], name: 'uniq_push_endpoint')]
class PushSubscription
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private User $user;

    #[ORM\Column(type: 'text')]
    private string $endpoint;

    #[ORM\Column(type: 'string', length: 255)]
    private string $publicKey;

    #[ORM\Column(type: 'string', length: 255)]
    private string $authToken;

    #[ORM\Column(type: 'string', length: 50, nullable: true)]
    private ?string $contentEncoding = 'aesgcm';

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $lastUsedAt = null;

    public function __construct(User $user, string $endpoint, string $publicKey, string $authToken, ?string $contentEncoding = 'aesgcm')
    {
        $this->user = $user;
        $this->endpoint = $endpoint;
        $this->publicKey = $publicKey;
        $this->authToken = $authToken;
        $this->contentEncoding = $contentEncoding;
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int { return $this->id; }
    public function getUser(): User { return $this->user; }
    public function getEndpoint(): string { return $this->endpoint; }
    public function getPublicKey(): string { return $this->publicKey; }
    public function getAuthToken(): string { return $this->authToken; }
    public function getContentEncoding(): ?string { return $this->contentEncoding; }
    public function getCreatedAt(): \DateTimeImmutable { return $this->createdAt; }
    public function getLastUsedAt(): ?\DateTimeImmutable { return $this->lastUsedAt; }

    public function markUsed(): void
    {
        $this->lastUsedAt = new \DateTimeImmutable();
    }
}
