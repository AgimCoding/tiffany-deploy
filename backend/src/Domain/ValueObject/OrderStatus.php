<?php

declare(strict_types=1);

namespace App\Domain\ValueObject;

enum OrderStatus: string
{
    case PENDING = 'pending';
    case CONFIRMED = 'confirmed';
    case READY = 'ready';
    case COMPLETED = 'completed';
    case CANCELLED = 'cancelled';
}
