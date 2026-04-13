<?php

declare(strict_types=1);

namespace App\Enum;

enum PromotionType: string
{
    case ProductAutomatic = 'product_automatic';
    case CartAutomatic = 'cart_automatic';
    case CartCode = 'cart_code';
    case PrivateLink = 'private_link';

    public function label(): string
    {
        return match ($this) {
            self::ProductAutomatic => 'Promo produit (automatique)',
            self::CartAutomatic => 'Promo panier (automatique)',
            self::CartCode => 'Code promo (panier)',
            self::PrivateLink => 'Lien privé',
        };
    }

    public function requiresCode(): bool
    {
        return match ($this) {
            self::CartCode, self::PrivateLink => true,
            default => false,
        };
    }
}
