<?php

declare(strict_types=1);

namespace App\Twig;

use App\Entity\Product;
use App\Entity\Promotion;
use App\Service\PromotionEngine;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

class PromotionExtension extends AbstractExtension
{
    public function __construct(
        private readonly PromotionEngine $promotionEngine,
    ) {
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('product_promo', $this->getProductPromotion(...)),
            new TwigFunction('product_promo_price', $this->getDiscountedDisplayPrice(...)),
            new TwigFunction('product_compare_at_price', $this->getEffectiveCompareAtPrice(...)),
            new TwigFunction('product_discount_percent', $this->getEffectiveDiscountPercent(...)),
        ];
    }

    public function getProductPromotion(Product $product): ?Promotion
    {
        return $this->promotionEngine->getBestProductPromotion($product);
    }

    public function getDiscountedDisplayPrice(Product $product): ?float
    {
        return $this->promotionEngine->getDiscountedDisplayPrice($product);
    }

    public function getEffectiveCompareAtPrice(Product $product): ?float
    {
        return $this->promotionEngine->getEffectiveCompareAtPrice($product);
    }

    public function getEffectiveDiscountPercent(Product $product): ?int
    {
        return $this->promotionEngine->getEffectiveDiscountPercent($product);
    }
}
