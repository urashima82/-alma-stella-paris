<?php

declare(strict_types=1);

namespace App\Controller\Shop;

use App\Repository\ProductRepository;
use App\Service\CartManager;
use App\Service\CurrencyConverter;
use App\Service\PromotionEngine;
use App\Service\ShippingCostProvider;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class CartController extends AbstractController
{
    public function __construct(
        private readonly CartManager $cartManager,
        private readonly ProductRepository $productRepository,
        private readonly CurrencyConverter $currencyConverter,
        private readonly ShippingCostProvider $shippingCostProvider,
        private readonly PromotionEngine $promotionEngine,
    ) {
    }

    #[Route('/cart/add/{id}', name: 'cart_add', methods: ['POST'])]
    public function add(int $id, Request $request): JsonResponse
    {
        if (!$this->isCsrfTokenValid('submit', $request->headers->get('X-CSRF-Token'))) {
            return $this->json(['success' => false, 'message' => 'invalid_csrf'], Response::HTTP_FORBIDDEN);
        }

        if ($this->cartManager->contains($id)) {
            return $this->json([
                'success' => false,
                'message' => 'already_in_cart',
                'count' => $this->cartManager->count(),
            ]);
        }

        $product = $this->productRepository->find($id);

        if ($product === null) {
            return $this->json(['success' => false, 'message' => 'not_found'], Response::HTTP_NOT_FOUND);
        }

        $added = $this->cartManager->add($product);

        if (!$added) {
            $reason = $product->isSoldOut() ? 'sold_out' : 'reserved';

            return $this->json([
                'success' => false,
                'message' => $reason,
                'count' => $this->cartManager->count(),
            ]);
        }

        return $this->json([
            'success' => true,
            'count' => $this->cartManager->count(),
        ]);
    }

    #[Route('/cart/remove/{id}', name: 'cart_remove', methods: ['POST'])]
    public function remove(int $id, Request $request): JsonResponse
    {
        if (!$this->isCsrfTokenValid('submit', $request->headers->get('X-CSRF-Token'))) {
            return $this->json(['success' => false, 'message' => 'invalid_csrf'], Response::HTTP_FORBIDDEN);
        }

        $this->cartManager->remove($id);

        return $this->json([
            'success' => true,
            'count' => $this->cartManager->count(),
        ]);
    }

    #[Route('/cart', name: 'cart_content', methods: ['GET'])]
    public function content(Request $request): JsonResponse
    {
        $products = $this->cartManager->getProducts();
        $locale = $request->getLocale();
        $currency = $request->getSession()->get('_currency', CurrencyConverter::BASE_CURRENCY);

        $items = [];
        foreach ($products as $product) {
            $displayPrice = $this->shippingCostProvider->getDisplayPrice($product->getBasePrice(), $product->getShippingTier());
            $promoPrice = $this->promotionEngine->getDiscountedDisplayPrice($product);
            $effectivePrice = $promoPrice ?? $displayPrice;
            $compareAtPrice = $this->promotionEngine->getEffectiveCompareAtPrice($product);
            $convertedPrice = $this->currencyConverter->convert($effectivePrice, $currency);

            $item = [
                'id' => $product->getId(),
                'name' => $locale === 'fr' ? $product->getNameFr() : $product->getName(),
                'priceFormatted' => $this->formatPrice($convertedPrice, $currency),
                'image' => $product->getThumbnail() !== null
                    ? '/uploads/products/'.$product->getThumbnail()
                    : null,
                'slug' => $locale === 'fr' ? $product->getSlugFr() : $product->getSlug(),
                'hasDiscount' => $compareAtPrice !== null,
            ];

            if ($compareAtPrice !== null) {
                $item['originalPriceFormatted'] = $this->formatPrice(
                    $this->currencyConverter->convert($compareAtPrice, $currency),
                    $currency,
                );
            }

            $items[] = $item;
        }

        $subtotalEur = $this->promotionEngine->getEffectiveSubtotalEur($products);
        $subtotalConverted = $this->currencyConverter->convert($subtotalEur, $currency);

        return $this->json([
            'items' => $items,
            'count' => \count($items),
            'subtotalFormatted' => $this->formatPrice($subtotalConverted, $currency),
            'currency' => $currency,
        ]);
    }

    private function formatPrice(float $amount, string $currency): string
    {
        $symbol = CurrencyConverter::getSymbol($currency);
        $formatted = \number_format($amount, 2);

        // Strip trailing .00 for whole numbers
        if (\str_ends_with($formatted, '.00')) {
            $formatted = \substr($formatted, 0, -3);
        }

        return $symbol.$formatted;
    }
}
