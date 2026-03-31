<?php

declare(strict_types=1);

namespace App\Domain\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'gift_cards')]
class GiftCard
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\Column(type: 'string', length: 20, unique: true)]
    private string $code;

    #[ORM\Column(type: 'decimal', precision: 8, scale: 2)]
    private string $amount;

    #[ORM\Column(type: 'decimal', precision: 8, scale: 2)]
    private string $balance;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?User $purchasedBy = null;

    #[ORM\Column(type: 'string', length: 255)]
    private string $recipientName;

    #[ORM\Column(type: 'string', length: 255)]
    private string $recipientEmail;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $message = null;

    #[ORM\Column(type: 'string', length: 20)]
    private string $status = 'active'; // active, used, expired

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $expiresAt;

    public function __construct(string $amount, string $recipientName, string $recipientEmail, ?string $message = null, ?User $purchasedBy = null)
    {
        $this->code = strtoupper(substr(bin2hex(random_bytes(5)), 0, 10));
        $this->amount = $amount;
        $this->balance = $amount;
        $this->recipientName = $recipientName;
        $this->recipientEmail = $recipientEmail;
        $this->message = $message;
        $this->purchasedBy = $purchasedBy;
        $this->createdAt = new \DateTimeImmutable();
        $this->expiresAt = new \DateTimeImmutable('+1 year');
    }

    public function getId(): ?int { return $this->id; }
    public function getCode(): string { return $this->code; }
    public function getAmount(): string { return $this->amount; }
    public function getBalance(): string { return $this->balance; }
    public function getPurchasedBy(): ?User { return $this->purchasedBy; }
    public function getRecipientName(): string { return $this->recipientName; }
    public function getRecipientEmail(): string { return $this->recipientEmail; }
    public function getMessage(): ?string { return $this->message; }
    public function getStatus(): string { return $this->status; }
    public function getCreatedAt(): \DateTimeImmutable { return $this->createdAt; }
    public function getExpiresAt(): \DateTimeImmutable { return $this->expiresAt; }

    public function isValid(): bool
    {
        return $this->status === 'active'
            && (float) $this->balance > 0
            && $this->expiresAt > new \DateTimeImmutable();
    }

    public function debit(string $amount): void
    {
        $newBalance = bcsub($this->balance, $amount, 2);
        if (bccomp($newBalance, '0', 2) < 0) {
            throw new \DomainException('Solde insuffisant sur la carte cadeau.');
        }
        $this->balance = $newBalance;
        if (bccomp($this->balance, '0', 2) === 0) {
            $this->status = 'used';
        }
    }
}
