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
        private readonly ShippingCostProvider $shippingCostProvider,
    ) {
    }

    /**
     * The customer-facing price: base price plus the shipping share of the
     * product's tier, read from the admin-configured ShippingSettings — never
     * from the ShippingTier enum defaults.
     */
    private function displayPrice(Product $product): float
    {
        return $this->shippingCostProvider->getDisplayPrice($product->getBasePrice(), $product->getShippingTier());
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

        $displayPrice = $this->displayPrice($product);

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

        $displayPrice = $this->displayPrice($product);

        return \round($displayPrice - $promo->calculateDiscount($displayPrice), 2);
    }

    /**
     * Calculate the effective subtotal for a set of products,
     * accounting for product-level promotions.
     *
     * @param Product[] $products
     */
    public function getEffectiveSubtotalEur(array $products): float
    {
        $total = 0.0;

        foreach ($products as $product) {
            $discountedPrice = $this->getDiscountedDisplayPrice($product);
            $total += $discountedPrice ?? $this->displayPrice($product);
        }

        return $total;
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
            return $this->displayPrice($product);
        }

        if ($product->getCompareAtPrice() !== null) {
            return $product->getCompareAtPrice() + $product->getShippingTier()->shippingCostEur();
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
            $displayPrice = $this->displayPrice($product);
            $discount = $promo->calculateDiscount($displayPrice);

            return (int) \round(($discount / $displayPrice) * 100);
        }

        return $product->getDiscountPercent();
    }

    /**
     * Evaluate cart-level promotions (automatic + code).
     *
     * Auto promos and coupon codes are independent: they always stack.
     * Within auto promos, cumulable ones stack; non-cumulable ones compete.
     * Only one coupon code at a time; it applies only to full-price items
     * (no product-level promo, no manual compareAtPrice).
     *
     * @param Product[] $products
     *
     * @return array{promotions: list<array{promotion: Promotion, discount: float}>, totalDiscount: float}
     */
    public function evaluateCartPromotions(array $products, float $subtotalEur, ?string $couponCode = null, ?string $customerEmail = null): array
    {
        $results = [];
        $totalDiscount = 0.0;

        // --- Phase 1: Automatic cart promotions (compete among themselves) ---
        $autoResults = [];
        $autoCumulableDiscount = 0.0;
        $bestAutoNonCumulable = null;
        $bestAutoNonCumulableDiscount = 0.0;

        foreach ($this->getActiveCartPromotions() as $promo) {
            $discount = $this->evaluateSingleCartPromotion($promo, $products, $subtotalEur, $customerEmail);

            if ($discount <= 0.0) {
                continue;
            }

            if ($promo->isCumulable()) {
                $autoCumulableDiscount += $discount;
                $autoResults[] = ['promotion' => $promo, 'discount' => $discount];
            } elseif ($discount > $bestAutoNonCumulableDiscount) {
                $bestAutoNonCumulableDiscount = $discount;
                $bestAutoNonCumulable = $promo;
            }
        }

        if ($bestAutoNonCumulable !== null && $bestAutoNonCumulableDiscount > $autoCumulableDiscount) {
            $results[] = ['promotion' => $bestAutoNonCumulable, 'discount' => $bestAutoNonCumulableDiscount];
            $totalDiscount += $bestAutoNonCumulableDiscount;
        } else {
            \array_push($results, ...$autoResults);
            $totalDiscount += $autoCumulableDiscount;
        }

        // --- Phase 2: Coupon code (always stacks with auto promos, applies only to full-price items) ---
        if ($couponCode !== null && $couponCode !== '') {
            $codePromo = $this->validateCouponCode($couponCode, $subtotalEur, $customerEmail);

            if ($codePromo !== null) {
                $eligibleTotal = $this->getCouponEligibleSubtotal($codePromo, $products);
                $discount = $eligibleTotal > 0.0 ? $codePromo->calculateDiscount($eligibleTotal) : 0.0;

                if ($discount > 0.0) {
                    $results[] = ['promotion' => $codePromo, 'discount' => $discount];
                    $totalDiscount += $discount;
                }
            }
        }

        return [
            'promotions' => $results,
            'totalDiscount' => $totalDiscount,
        ];
    }

    /**
     * Validate a coupon code and return the Promotion if valid, null otherwise.
     */
    public function validateCouponCode(string $code, float $subtotalEur = 0.0, ?string $customerEmail = null): ?Promotion
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

        if ($promo->getMinimumAmountEur() !== null && $subtotalEur < $promo->getMinimumAmountEur()) {
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
            $promo->addRevenue($order->getTotalEur());
            $promo->setLastUsedAt(new \DateTimeImmutable());

            $this->entityManager->persist($usage);
        }

        $this->entityManager->flush();
    }

    /**
     * @param Product[] $products
     */
    private function evaluateSingleCartPromotion(Promotion $promo, array $products, float $subtotalEur, ?string $customerEmail): float
    {
        if (!$promo->isCurrentlyValid()) {
            return 0.0;
        }

        if ($promo->hasReachedMaxUsages()) {
            return 0.0;
        }

        if ($promo->getMinimumAmountEur() !== null && $subtotalEur < $promo->getMinimumAmountEur()) {
            return 0.0;
        }

        if ($customerEmail !== null && $promo->getMaxUsagesPerEmail() !== null) {
            $emailUsages = $this->promotionRepository->countUsagesByEmail($promo, $customerEmail);
            if ($emailUsages >= $promo->getMaxUsagesPerEmail()) {
                return 0.0;
            }
        }

        $eligibleTotal = $this->getEligibleSubtotal($promo, $products, $subtotalEur);

        if ($eligibleTotal <= 0.0) {
            return 0.0;
        }

        return $promo->calculateDiscount($eligibleTotal);
    }

    /**
     * Calculate the subtotal eligible for an automatic cart promotion.
     * Cumulable auto promos without restrictions use the full subtotal.
     * Non-cumulable auto promos exclude already-discounted products.
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

            if (!$promo->isCumulable() && $this->isAlreadyDiscounted($product, $promo)) {
                continue;
            }

            $effectivePrice = $this->getDiscountedDisplayPrice($product) ?? $this->displayPrice($product);
            $total += $effectivePrice;
        }

        return $total;
    }

    /**
     * Calculate the subtotal eligible for a coupon code.
     * Coupons always apply only to full-price items (no product-level
     * promotion and no manual compareAtPrice), regardless of cumulability.
     *
     * @param Product[] $products
     */
    private function getCouponEligibleSubtotal(Promotion $promo, array $products): float
    {
        $hasRestriction = !$promo->getProducts()->isEmpty() || !$promo->getCategories()->isEmpty();
        $total = 0.0;

        foreach ($products as $product) {
            if ($hasRestriction && !$promo->appliesToProduct($product)) {
                continue;
            }

            if ($this->isAlreadyDiscounted($product, $promo)) {
                continue;
            }

            $total += $this->displayPrice($product);
        }

        return $total;
    }

    private function isAlreadyDiscounted(Product $product, Promotion $promo): bool
    {
        if ($this->getBestProductPromotion($product) !== null) {
            return true;
        }

        if ($product->getCompareAtPrice() !== null && !$promo->overridesCompareAtPrice()) {
            return true;
        }

        return false;
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
