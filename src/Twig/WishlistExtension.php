<?php

declare(strict_types=1);

namespace App\Twig;

use App\Service\WishlistManager;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

class WishlistExtension extends AbstractExtension
{
    public function __construct(
        private readonly WishlistManager $wishlistManager,
    ) {
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('wishlist_product_ids', $this->getWishlistedProductIds(...)),
            new TwigFunction('wishlist_count', $this->getWishlistCount(...)),
        ];
    }

    /**
     * @return int[]
     */
    public function getWishlistedProductIds(): array
    {
        return $this->wishlistManager->getWishlistedProductIds();
    }

    public function getWishlistCount(): int
    {
        return $this->wishlistManager->count();
    }
}
