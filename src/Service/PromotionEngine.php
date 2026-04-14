<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Order;
use App\Entity\Product;
use App\Entity\Promotion;
use App\Entity\PromotionUsage;
use App\Enum\PromotionType;
use App\Repository\PromotionRepository;
use Doctrine\ORM\EntityManagerInterface;

class PromotionEngine
{
    /** @var Promotion[]|null */
    private ?array $activeProductPromotions = null;

    /** @var Promotion[]|null */
    private ?array $activeCartPromotions = null;

    public function __construct(
        private readonly PromotionRepository $promotionRepository,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    /**
     * Get the best applicable product-level promotion for a product.
     * Returns null if no valid promotion applies.
     */
    public function getBestProductPromotion(Product $product): ?Promotion
    {
        $promos = $this->getActiveProductPromotions();
        $bestDiscount = 0.0;
        $bestPromo = null;

        $displayPrice = $product->getDisplayPrice();

        foreach ($promos as $promo) {
            if (!$promo->appliesToProduct($product)) {
                continue;
            }

            if (!$promo->canApplyToProductWithCompareAtPrice($product)) {
                continue;
            }

            if ($promo->hasReachedMaxUsages()) {
                continue;
            }

            $discount = $promo->calculateDiscount($displayPrice);

            if ($discount > $bestDiscount) {
                $bestDiscount = $discount;
                $bestPromo = $promo;
            }
        }

        return $bestPromo;
    }

    /**
     * Calculate the discounted display price for a product (including shipping).
     * Returns null if no promotion applies.
     */
    public function getDiscountedDisplayPrice(Product $product): ?float
    {
        $promo = $this->getBestProductPromotion($product);

        if ($promo === null) {
            return null;
        }

        $displayPrice = $product->getDisplayPrice();

        return \round($displayPrice - $promo->calculateDiscount($displayPrice), 2);
    }

    /**
     * Get the effective "compare at" price for a product.
     * Priority: active promotion > manual compareAtPrice > null.
     * When a promotion applies, the "compare at" is the original display price.
     */
    public function getEffectiveCompareAtPrice(Product $product): ?float
    {
        $promo = $this->getBestProductPromotion($product);

        if ($promo !== null) {
            return $product->getDisplayPrice();
        }

        if ($product->getCompareAtPrice() !== null) {
            return $product->getCompareAtPrice() + $product->getShippingTier()->shippingCostUsd();
        }

        return null;
    }

    /**
     * Get the effective discount percentage for display badge.
     */
    public function getEffectiveDiscountPercent(Product $product): ?int
    {
        $promo = $this->getBestProductPromotion($product);

        if ($promo !== null) {
            $displayPrice = $product->getDisplayPrice();
            $discount = $promo->calculateDiscount($displayPrice);

            return (int) \round(($discount / $displayPrice) * 100);
        }

        return $product->getDiscountPercent();
    }

    /**
     * Evaluate cart-level promotions (automatic + code).
     *
     * @param Product[] $products
     *
     * @return array{promotions: list<array{promotion: Promotion, discount: float}>, totalDiscount: float}
     */
    public function evaluateCartPromotions(array $products, float $subtotalUsd, ?string $couponCode = null, ?string $customerEmail = null): array
    {
        $results = [];
        $cumulableDiscount = 0.0;
        $bestNonCumulable = null;
        $bestNonCumulableDiscount = 0.0;

        // Automatic cart promotions
        foreach ($this->getActiveCartPromotions() as $promo) {
            $discount = $this->evaluateSingleCartPromotion($promo, $products, $subtotalUsd, $customerEmail);

            if ($discount <= 0.0) {
                continue;
            }

            if ($promo->isCumulable()) {
                $cumulableDiscount += $discount;
                $results[] = ['promotion' => $promo, 'discount' => $discount];
            } elseif ($discount > $bestNonCumulableDiscount) {
                $bestNonCumulableDiscount = $discount;
                $bestNonCumulable = $promo;
            }
        }

        // Coupon code (CartCode)
        if ($couponCode !== null && $couponCode !== '') {
            $codePromo = $this->validateCouponCode($couponCode, $subtotalUsd, $customerEmail);

            if ($codePromo !== null) {
                $eligibleTotal = $this->getEligibleSubtotal($codePromo, $products, $subtotalUsd);
                $discount = $eligibleTotal > 0.0 ? $codePromo->calculateDiscount($eligibleTotal) : 0.0;

                if ($discount > 0.0) {
                    if ($codePromo->isCumulable()) {
                        $cumulableDiscount += $discount;
                        $results[] = ['promotion' => $codePromo, 'discount' => $discount];
                    } elseif ($discount > $bestNonCumulableDiscount) {
                        $bestNonCumulableDiscount = $discount;
                        $bestNonCumulable = $codePromo;
                    }
                }
            }
        }

        // Best non-cumulable vs sum of cumulables
        if ($bestNonCumulable !== null) {
            if ($bestNonCumulableDiscount > $cumulableDiscount) {
                return [
                    'promotions' => [['promotion' => $bestNonCumulable, 'discount' => $bestNonCumulableDiscount]],
                    'totalDiscount' => $bestNonCumulableDiscount,
                ];
            }
        }

        return [
            'promotions' => $results,
            'totalDiscount' => $cumulableDiscount,
        ];
    }

    /**
     * Validate a coupon code and return the Promotion if valid, null otherwise.
     */
    public function validateCouponCode(string $code, float $subtotalUsd = 0.0, ?string $customerEmail = null): ?Promotion
    {
        $promo = $this->promotionRepository->findByCode($code);

        if ($promo === null) {
            return null;
        }

        if (!$promo->isCurrentlyValid()) {
            return null;
        }

        if ($promo->getType() !== PromotionType::CartCode) {
            return null;
        }

        if ($promo->hasReachedMaxUsages()) {
            return null;
        }

        if ($promo->getMinimumAmountUsd() !== null && $subtotalUsd < $promo->getMinimumAmountUsd()) {
            return null;
        }

        if ($customerEmail !== null && $promo->getMaxUsagesPerEmail() !== null) {
            $emailUsages = $this->promotionRepository->countUsagesByEmail($promo, $customerEmail);
            if ($emailUsages >= $promo->getMaxUsagesPerEmail()) {
                return null;
            }
        }

        return $promo;
    }

    /**
     * Record promotion usage after successful payment.
     *
     * @param list<array{promotion: Promotion, discount: float}> $appliedPromotions
     */
    public function recordUsage(Order $order, array $appliedPromotions): void
    {
        foreach ($appliedPromotions as $entry) {
            $promo = $entry['promotion'];
            $discount = $entry['discount'];

            $usage = PromotionUsage::create(
                $promo,
                $order,
                $order->getCustomerEmail(),
                $discount,
            );

            $promo->incrementUsageCount();
            $promo->addRevenue($order->getTotalUsd());
            $promo->setLastUsedAt(new \DateTimeImmutable());

            $this->entityManager->persist($usage);
        }

        $this->entityManager->flush();
    }

    /**
     * @param Product[] $products
     */
    private function evaluateSingleCartPromotion(Promotion $promo, array $products, float $subtotalUsd, ?string $customerEmail): float
    {
        if (!$promo->isCurrentlyValid()) {
            return 0.0;
        }

        if ($promo->hasReachedMaxUsages()) {
            return 0.0;
        }

        if ($promo->getMinimumAmountUsd() !== null && $subtotalUsd < $promo->getMinimumAmountUsd()) {
            return 0.0;
        }

        if ($customerEmail !== null && $promo->getMaxUsagesPerEmail() !== null) {
            $emailUsages = $this->promotionRepository->countUsagesByEmail($promo, $customerEmail);
            if ($emailUsages >= $promo->getMaxUsagesPerEmail()) {
                return 0.0;
            }
        }

        $eligibleTotal = $this->getEligibleSubtotal($promo, $products, $subtotalUsd);

        if ($eligibleTotal <= 0.0) {
            return 0.0;
        }

        return $promo->calculateDiscount($eligibleTotal);
    }

    /**
     * Calculate the subtotal eligible for a cart promotion.
     * Non-cumulable promotions exclude products already discounted
     * (active product promotion or manual compareAtPrice).
     *
     * @param Product[] $products
     */
    private function getEligibleSubtotal(Promotion $promo, array $products, float $fullSubtotal): float
    {
        $hasRestriction = !$promo->getProducts()->isEmpty() || !$promo->getCategories()->isEmpty();

        if ($promo->isCumulable() && !$hasRestriction) {
            return $fullSubtotal;
        }

        $total = 0.0;

        foreach ($products as $product) {
            if ($hasRestriction && !$promo->appliesToProduct($product)) {
                continue;
            }

            if (!$promo->isCumulable() && !$this->isEligibleForNonCumulable($promo, $product)) {
                continue;
            }

            $total += $product->getDisplayPrice();
        }

        return $total;
    }

    private function isEligibleForNonCumulable(Promotion $promo, Product $product): bool
    {
        if ($this->getBestProductPromotion($product) !== null) {
            return false;
        }

        if ($product->getCompareAtPrice() !== null && !$promo->overridesCompareAtPrice()) {
            return false;
        }

        return true;
    }

    /**
     * @return Promotion[]
     */
    private function getActiveProductPromotions(): array
    {
        if ($this->activeProductPromotions === null) {
            $this->activeProductPromotions = $this->promotionRepository->findActiveAutoProductPromotions();
        }

        return $this->activeProductPromotions;
    }

    /**
     * @return Promotion[]
     */
    private function getActiveCartPromotions(): array
    {
        if ($this->activeCartPromotions === null) {
            $this->activeCartPromotions = $this->promotionRepository->findActiveAutoCartPromotions();
        }

        return $this->activeCartPromotions;
    }
}
