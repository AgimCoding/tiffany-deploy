<?php

declare(strict_types=1);

namespace App\Domain\Entity;

use App\Domain\ValueObject\OrderStatus;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'orders')]
class Order
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: User::class, inversedBy: 'orders')]
    #[ORM\JoinColumn(nullable: false)]
    private User $user;

    #[ORM\Column(type: 'decimal', precision: 10, scale: 2)]
    private string $total = '0.00';

    #[ORM\Column(type: 'string', length: 30)]
    private string $status;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    #[ORM\OneToMany(targetEntity: OrderItem::class, mappedBy: 'order', cascade: ['persist', 'remove'])]
    private Collection $items;

    public function __construct(User $user)
    {
        $this->user = $user;
        $this->status = OrderStatus::PENDING->value;
        $this->createdAt = new \DateTimeImmutable();
        $this->items = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getUser(): User
    {
        return $this->user;
    }

    public function getTotal(): string
    {
        return $this->total;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function confirm(): void
    {
        $this->status = OrderStatus::CONFIRMED->value;
    }

    public function markReady(): void
    {
        $this->status = OrderStatus::READY->value;
    }

    public function complete(): void
    {
        $this->status = OrderStatus::COMPLETED->value;
    }

    public function cancel(): void
    {
        $this->status = OrderStatus::CANCELLED->value;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getItems(): Collection
    {
        return $this->items;
    }

    public function addItem(Product $product, int $quantity): void
    {
        $item = new OrderItem($this, $product, $quantity, $product->getPrice());
        $this->items->add($item);
        $this->recalculateTotal();
    }

    private function recalculateTotal(): void
    {
        $total = '0.00';
        foreach ($this->items as $item) {
            $total = bcadd($total, bcmul($item->getPrice(), (string) $item->getQuantity(), 2), 2);
        }
        $this->total = $total;
    }
}
