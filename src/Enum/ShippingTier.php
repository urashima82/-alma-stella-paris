<?php

declare(strict_types=1);

namespace App\Enum;

enum ShippingTier: string
{
    case Standard = 'standard';
    case Heavy = 'heavy';
    case Set = 'set';

    public function label(): string
    {
        return match ($this) {
            self::Standard => 'Pièce légère',
            self::Heavy => 'Pièce forte',
            self::Set => 'Set / Coffret',
        };
    }

    public function shippingCostUsd(): float
    {
        return match ($this) {
            self::Standard => 10.00,
            self::Heavy => 14.00,
            self::Set => 22.00,
        };
    }

    public function maxWeightGrams(): int
    {
        return match ($this) {
            self::Standard => 100,
            self::Heavy => 350,
            self::Set => 1000,
        };
    }

    public function badgeColor(): string
    {
        return match ($this) {
            self::Standard => '#22c55e',
            self::Heavy => '#f97316',
            self::Set => '#3b82f6',
        };
    }
}
