<?php

declare(strict_types=1);

namespace App\Enum;

enum OrderStatus: string
{
    case Pending = 'pending';
    case Processing = 'processing';
    case Shipped = 'shipped';
    case Delivered = 'delivered';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'En attente',
            self::Processing => 'En préparation',
            self::Shipped => 'Expédiée',
            self::Delivered => 'Livrée',
            self::Cancelled => 'Annulée',
        };
    }

    public function badgeColor(): string
    {
        return match ($this) {
            self::Pending => '#f59e0b',
            self::Processing => '#3b82f6',
            self::Shipped => '#8b5cf6',
            self::Delivered => '#22c55e',
            self::Cancelled => '#ef4444',
        };
    }
}
