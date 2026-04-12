<?php

declare(strict_types=1);

namespace App\Twig;

use App\Entity\Product;
use App\Enum\ShippingTier;
use App\Service\ShippingCostProvider;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

class ShippingExtension extends AbstractExtension
{
    public function __construct(
        private readonly ShippingCostProvider $shippingCostProvider,
    ) {
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('shipping_cost', $this->getShippingCost(...)),
            new TwigFunction('shipping_label', $this->getShippingLabel(...)),
            new TwigFunction('display_price', $this->getDisplayPrice(...)),
            new TwigFunction('display_compare_at_price', $this->getDisplayCompareAtPrice(...)),
            new TwigFunction('discount_percent', $this->getDiscountPercent(...)),
        ];
    }

    public function getShippingCost(ShippingTier $tier): float
    {
        return $this->shippingCostProvider->getCost($tier);
    }

    public function getShippingLabel(ShippingTier $tier): string
    {
        return $this->shippingCostProvider->getLabel($tier);
    }

    public function getDisplayPrice(Product $product): float
    {
        return $this->shippingCostProvider->getDisplayPrice(
            $product->getBasePrice(),
            $product->getShippingTier(),
        );
    }

    public function getDisplayCompareAtPrice(Product $product): ?float
    {
        $compareAtPrice = $product->getCompareAtPrice();

        if (null === $compareAtPrice) {
            return null;
        }

        return $this->shippingCostProvider->getDisplayPrice(
            $compareAtPrice,
            $product->getShippingTier(),
        );
    }

    public function getDiscountPercent(Product $product): ?int
    {
        return $product->getDiscountPercent();
    }
}
