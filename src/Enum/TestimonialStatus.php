<?php

declare(strict_types=1);

namespace App\Enum;

enum TestimonialStatus: string
{
    case Pending = 'pending';
    case Approved = 'approved';
    case Rejected = 'rejected';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'En attente',
            self::Approved => 'Approuvé',
            self::Rejected => 'Rejeté',
        };
    }

    public function badgeColor(): string
    {
        return match ($this) {
            self::Pending => '#f59e0b',
            self::Approved => '#22c55e',
            self::Rejected => '#ef4444',
        };
    }
}
