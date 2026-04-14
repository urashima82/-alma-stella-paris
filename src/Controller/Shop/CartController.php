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
    public function add(int $id): JsonResponse
    {
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
    public function remove(int $id): JsonResponse
    {
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
            $convertedPrice = $this->currencyConverter->convert($effectivePrice, $currency);

            $item = [
                'id' => $product->getId(),
                'name' => $locale === 'fr' ? $product->getNameFr() : $product->getName(),
                'priceFormatted' => $this->formatPrice($convertedPrice, $currency),
                'image' => $product->getThumbnail() !== null
                    ? '/uploads/products/'.$product->getThumbnail()
                    : null,
                'slug' => $locale === 'fr' ? $product->getSlugFr() : $product->getSlug(),
                'hasDiscount' => $promoPrice !== null,
            ];

            if ($promoPrice !== null) {
                $item['originalPriceFormatted'] = $this->formatPrice(
                    $this->currencyConverter->convert($displayPrice, $currency),
                    $currency,
                );
            }

            $items[] = $item;
        }

        $subtotalUsd = $this->cartManager->getSubtotalUsd();
        $subtotalConverted = $this->currencyConverter->convert($subtotalUsd, $currency);

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
