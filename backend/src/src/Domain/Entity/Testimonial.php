<?php

declare(strict_types=1);

namespace App\Domain\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'testimonials')]
class Testimonial
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\Column(type: 'string', length: 255)]
    private string $clientName;

    #[ORM\Column(type: 'text')]
    private string $message;

    #[ORM\Column(type: 'integer')]
    private int $rating;

    #[ORM\Column(type: 'boolean')]
    private bool $published;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    public function __construct(string $clientName, string $message, int $rating, bool $published = false)
    {
        $this->clientName = $clientName;
        $this->message = $message;
        $this->rating = min(5, max(1, $rating));
        $this->published = $published;
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int { return $this->id; }
    public function getClientName(): string { return $this->clientName; }
    public function setClientName(string $v): void { $this->clientName = $v; }
    public function getMessage(): string { return $this->message; }
    public function setMessage(string $v): void { $this->message = $v; }
    public function getRating(): int { return $this->rating; }
    public function setRating(int $v): void { $this->rating = min(5, max(1, $v)); }
    public function isPublished(): bool { return $this->published; }
    public function setPublished(bool $v): void { $this->published = $v; }
    public function getCreatedAt(): \DateTimeImmutable { return $this->createdAt; }
}
