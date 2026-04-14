<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Customer;
use App\Entity\Product;
use App\Entity\WishlistItem;
use App\Repository\WishlistItemRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;

class WishlistManager
{
    public function __construct(
        private readonly Security $security,
        private readonly WishlistItemRepository $wishlistItemRepository,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    /**
     * Toggle a product in the wishlist. Returns true if the product is now wishlisted.
     */
    public function toggle(Product $product): bool
    {
        $customer = $this->getCustomer();

        if ($customer === null) {
            return false;
        }

        $existing = $this->wishlistItemRepository->findByCustomerAndProduct($customer, $product);

        if ($existing !== null) {
            $this->entityManager->remove($existing);
            $this->entityManager->flush();

            return false;
        }

        $item = new WishlistItem($customer, $product);
        $this->entityManager->persist($item);
        $this->entityManager->flush();

        return true;
    }

    public function isWishlisted(Product $product): bool
    {
        $customer = $this->getCustomer();

        if ($customer === null) {
            return false;
        }

        return $this->wishlistItemRepository->findByCustomerAndProduct($customer, $product) !== null;
    }

    /**
     * @return WishlistItem[]
     */
    public function getVisibleItems(): array
    {
        $customer = $this->getCustomer();

        if ($customer === null) {
            return [];
        }

        return $this->wishlistItemRepository->findVisibleByCustomer($customer);
    }

    /**
     * @return int[]
     */
    public function getWishlistedProductIds(): array
    {
        $customer = $this->getCustomer();

        if ($customer === null) {
            return [];
        }

        return $this->wishlistItemRepository->getProductIdsByCustomer($customer);
    }

    public function count(): int
    {
        $customer = $this->getCustomer();

        if ($customer === null) {
            return 0;
        }

        return \count($this->wishlistItemRepository->findVisibleByCustomer($customer));
    }

    /**
     * Count sold/unpublished items and clean them up. Returns the number of removed items.
     */
    public function cleanSoldItems(): int
    {
        $customer = $this->getCustomer();

        if ($customer === null) {
            return 0;
        }

        $soldCount = $this->wishlistItemRepository->countSoldByCustomer($customer);

        if ($soldCount > 0) {
            $this->wishlistItemRepository->deleteSoldAndUnpublished($customer);
        }

        return $soldCount;
    }

    private function getCustomer(): ?Customer
    {
        $user = $this->security->getUser();

        if (!$user instanceof Customer) {
            return null;
        }

        return $user;
    }
}
