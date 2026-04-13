<?php

declare(strict_types=1);

namespace App\Enum;

enum DiscountType: string
{
    case Percentage = 'percentage';
    case FixedAmount = 'fixed_amount';

    public function label(): string
    {
        return match ($this) {
            self::Percentage => 'Pourcentage (%)',
            self::FixedAmount => 'Montant fixe ($)',
        };
    }
}
