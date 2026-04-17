<?php

declare(strict_types=1);

namespace App\Enum;

enum VisualType: string
{
    case Vignette = 'vignette';
    case Worn = 'worn';
    case Lifestyle = 'lifestyle';

    public function label(): string
    {
        return match ($this) {
            self::Vignette => 'Vignette',
            self::Worn => 'Porté',
            self::Lifestyle => 'Lifestyle',
        };
    }

    public function badgeColor(): string
    {
        return match ($this) {
            self::Vignette => '#3b82f6',
            self::Worn => '#8b5cf6',
            self::Lifestyle => '#f59e0b',
        };
    }
}
