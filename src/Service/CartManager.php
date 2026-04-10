<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Product;
use App\Repository\ProductRepository;
use Symfony\Component\HttpFoundation\RequestStack;

class CartManager
{
    private const SESSION_KEY = '_cart';

    public function __construct(
        private readonly RequestStack $requestStack,
        private readonly ProductRepository $productRepository,
        private readonly ShippingCostProvider $shippingCostProvider,
    ) {
    }

    /**
     * Add a product to the cart. Returns false if the product is sold out or already in cart.
     */
    public function add(Product $product): bool
    {
        if ($product->isSoldOut()) {
            return false;
        }

        $cart = $this->getProductIds();

        if (\in_array($product->getId(), $cart, true)) {
            return false;
        }

        $cart[] = $product->getId();
        $this->save($cart);

        return true;
    }

    /**
     * Remove a product from the cart by its ID.
     */
    public function remove(int $productId): void
    {
        $cart = $this->getProductIds();
        $cart = \array_values(\array_filter($cart, static fn (int $id): bool => $id !== $productId));
        $this->save($cart);
    }

    /**
     * Clear the entire cart.
     */
    public function clear(): void
    {
        $this->save([]);
    }

    /**
     * Get the raw product IDs in the cart.
     *
     * @return int[]
     */
    public function getProductIds(): array
    {
        $session = $this->requestStack->getSession();

        /** @var int[] $cart */
        $cart = $session->get(self::SESSION_KEY, []);

        return $cart;
    }

    /**
     * Get hydrated Product entities for all items in the cart.
     * Automatically removes products that no longer exist or are sold out.
     *
     * @return Product[]
     */
    public function getProducts(): array
    {
        $ids = $this->getProductIds();

        if ([] === $ids) {
            return [];
        }

        $products = $this->productRepository->findBy(['id' => $ids]);

        // Filter out sold-out products and rebuild cart with valid IDs
        $validProducts = [];
        $validIds = [];

        foreach ($products as $product) {
            if (!$product->isSoldOut()) {
                $validProducts[] = $product;
                $validIds[] = $product->getId();
            }
        }

        // If the cart had stale entries, clean it up
        if (\count($validIds) !== \count($ids)) {
            $this->save($validIds);
        }

        return $validProducts;
    }

    /**
     * Get the number of items in the cart.
     */
    public function count(): int
    {
        return \count($this->getProductIds());
    }

    /**
     * Get the subtotal in USD (base currency) including shipping.
     */
    public function getSubtotalUsd(): float
    {
        $total = 0.0;

        foreach ($this->getProducts() as $product) {
            $total += $this->shippingCostProvider->getDisplayPrice($product->getBasePrice(), $product->getShippingTier());
        }

        return $total;
    }

    /**
     * Check if a product is already in the cart.
     */
    public function contains(int $productId): bool
    {
        return \in_array($productId, $this->getProductIds(), true);
    }

    /**
     * @param int[] $ids
     */
    private function save(array $ids): void
    {
        $this->requestStack->getSession()->set(self::SESSION_KEY, $ids);
    }
}
