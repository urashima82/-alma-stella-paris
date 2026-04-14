<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Cart;
use App\Entity\Customer;
use App\Entity\Product;
use App\Repository\CartRepository;
use App\Repository\ProductRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\RequestStack;

class CartManager
{
    private const SESSION_KEY = '_cart';
    private const COOKIE_NAME = 'alma_cart';
    private const COOKIE_LIFETIME_DAYS = 30;

    public function __construct(
        private readonly RequestStack $requestStack,
        private readonly ProductRepository $productRepository,
        private readonly ShippingCostProvider $shippingCostProvider,
        private readonly Security $security,
        private readonly CartRepository $cartRepository,
        private readonly EntityManagerInterface $entityManager,
        private readonly ReservationManager $reservationManager,
    ) {
    }

    /**
     * Add a product to the cart. Returns false if the product is sold out, reserved by another, or already in cart.
     */
    public function add(Product $product): bool
    {
        if ($product->isSoldOut() || $this->reservationManager->isReservedByOther($product)) {
            return false;
        }

        $customer = $this->getCustomer();

        if ($customer !== null) {
            $cart = $this->getOrCreateDbCart($customer);

            if ($cart->containsProduct($product->getId())) {
                return false;
            }

            $cart->addProduct($product);
            $this->entityManager->flush();

            return true;
        }

        // Guest: session + cookie
        $ids = $this->getGuestProductIds();

        if (\in_array($product->getId(), $ids, true)) {
            return false;
        }

        $ids[] = $product->getId();
        $this->saveGuest($ids);

        return true;
    }

    /**
     * Remove a product from the cart by its ID.
     */
    public function remove(int $productId): void
    {
        $customer = $this->getCustomer();

        if ($customer !== null) {
            $cart = $this->cartRepository->findByCustomer($customer);
            $cart?->removeProduct($productId);
            $this->entityManager->flush();

            return;
        }

        $ids = $this->getGuestProductIds();
        $ids = \array_values(\array_filter($ids, static fn (int $id): bool => $id !== $productId));
        $this->saveGuest($ids);
    }

    /**
     * Clear the entire cart.
     */
    public function clear(): void
    {
        $customer = $this->getCustomer();

        if ($customer !== null) {
            $cart = $this->cartRepository->findByCustomer($customer);
            $cart?->clear();
            $this->entityManager->flush();
        }

        // Always clear guest storage (session + cookie) to avoid stale data after logout
        $this->saveGuest([]);
    }

    /**
     * Get the raw product IDs in the cart.
     *
     * @return int[]
     */
    public function getProductIds(): array
    {
        $customer = $this->getCustomer();

        if ($customer !== null) {
            $cart = $this->cartRepository->findByCustomer($customer);

            return $cart?->getProductIds() ?? [];
        }

        return $this->getGuestProductIds();
    }

    /**
     * Get hydrated Product entities for all items in the cart.
     * Automatically removes products that are sold out or reserved by another session.
     *
     * @return Product[]
     */
    public function getProducts(): array
    {
        $ids = $this->getProductIds();

        if ($ids === []) {
            return [];
        }

        $products = $this->productRepository->findBy(['id' => $ids]);
        $reservedByOthers = $this->reservationManager->getReservedProductIdsByOthers();

        // Filter out sold-out and reserved-by-others products
        $validProducts = [];
        $validIds = [];

        foreach ($products as $product) {
            if (!$product->isSoldOut() && !\in_array($product->getId(), $reservedByOthers, true)) {
                $validProducts[] = $product;
                $validIds[] = $product->getId();
            }
        }

        // If the cart had stale entries, clean it up
        if (\count($validIds) !== \count($ids)) {
            $this->syncIds($validIds);
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
     * Merge guest cart (cookie/session) into the logged-in customer's DB cart.
     * Called on login to preserve items added before authentication.
     */
    public function mergeGuestCartIntoCustomer(Customer $customer): void
    {
        $guestIds = $this->getGuestProductIds();

        if ($guestIds === []) {
            return;
        }

        $cart = $this->getOrCreateDbCart($customer);
        $products = $this->productRepository->findBy(['id' => $guestIds]);

        foreach ($products as $product) {
            if (!$product->isSoldOut() && !$this->reservationManager->isReservedByOther($product)) {
                $cart->addProduct($product);
            }
        }

        $this->entityManager->flush();

        // Clear guest storage
        $this->saveGuest([]);
    }

    private function getCustomer(): ?Customer
    {
        $user = $this->security->getUser();

        return $user instanceof Customer ? $user : null;
    }

    private function getOrCreateDbCart(Customer $customer): Cart
    {
        $cart = $this->cartRepository->findByCustomer($customer);

        if ($cart === null) {
            $cart = new Cart();
            $cart->setCustomer($customer);
            $this->entityManager->persist($cart);
        }

        return $cart;
    }

    /**
     * @return int[]
     */
    private function getGuestProductIds(): array
    {
        // Try session first
        $session = $this->requestStack->getSession();
        /** @var int[] $sessionIds */
        $sessionIds = $session->get(self::SESSION_KEY, []);

        if ($sessionIds !== []) {
            return $sessionIds;
        }

        // Fall back to cookie
        $request = $this->requestStack->getCurrentRequest();

        if ($request === null) {
            return [];
        }

        $cookieValue = $request->cookies->get(self::COOKIE_NAME);

        if ($cookieValue === null || $cookieValue === '') {
            return [];
        }

        $decoded = \json_decode($cookieValue, true);

        if (!\is_array($decoded)) {
            return [];
        }

        $ids = \array_values(\array_filter(\array_map('\intval', $decoded), static fn (int $id): bool => $id > 0));

        // Restore into session from cookie
        if ($ids !== []) {
            $session->set(self::SESSION_KEY, $ids);
        }

        return $ids;
    }

    /**
     * @param int[] $ids
     */
    private function saveGuest(array $ids): void
    {
        $this->requestStack->getSession()->set(self::SESSION_KEY, $ids);
        $this->setCookieOnResponse($ids);
    }

    /**
     * @param int[] $ids
     */
    private function syncIds(array $ids): void
    {
        $customer = $this->getCustomer();

        if ($customer !== null) {
            $cart = $this->cartRepository->findByCustomer($customer);

            if ($cart !== null) {
                $cart->clear();
                $products = $this->productRepository->findBy(['id' => $ids]);

                foreach ($products as $product) {
                    $cart->addProduct($product);
                }

                $this->entityManager->flush();
            }

            return;
        }

        $this->saveGuest($ids);
    }

    /**
     * @param int[] $ids
     */
    private function setCookieOnResponse(array $ids): void
    {
        $request = $this->requestStack->getCurrentRequest();

        if ($request === null) {
            return;
        }

        $cookieValue = $ids === [] ? '' : \json_encode($ids, \JSON_THROW_ON_ERROR);
        $expire = $ids === [] ? 1 : \time() + (self::COOKIE_LIFETIME_DAYS * 86400);

        $cookie = Cookie::create(self::COOKIE_NAME)
            ->withValue($cookieValue)
            ->withExpires($expire)
            ->withPath('/')
            ->withSameSite('lax')
            ->withHttpOnly(false)
        ;

        // Store cookie in request attributes for the response listener to pick up
        /** @var Cookie[] $pendingCookies */
        $pendingCookies = $request->attributes->get('_pending_cookies', []);
        $pendingCookies[] = $cookie;
        $request->attributes->set('_pending_cookies', $pendingCookies);
    }
}
