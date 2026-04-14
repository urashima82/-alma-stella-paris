<?php

declare(strict_types=1);

namespace App\Controller\Shop;

use App\Entity\Customer;
use App\Repository\ProductRepository;
use App\Service\ReservationManager;
use App\Service\WishlistManager;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Contracts\Translation\TranslatorInterface;

class WishlistController extends AbstractController
{
    public function __construct(
        private readonly WishlistManager $wishlistManager,
        private readonly ProductRepository $productRepository,
        private readonly ReservationManager $reservationManager,
        private readonly TranslatorInterface $translator,
    ) {
    }

    #[Route('/wishlist/toggle/{id}', name: 'wishlist_toggle', methods: ['POST'])]
    #[IsGranted('ROLE_CUSTOMER')]
    public function toggle(int $id, Request $request): JsonResponse
    {
        if (!$this->isCsrfTokenValid('submit', $request->headers->get('X-CSRF-Token'))) {
            return $this->json(['success' => false, 'message' => 'invalid_csrf'], Response::HTTP_FORBIDDEN);
        }

        $product = $this->productRepository->find($id);

        if ($product === null) {
            return $this->json(['success' => false, 'message' => 'not_found'], Response::HTTP_NOT_FOUND);
        }

        if ($product->isSoldOut() || !$product->isPublished()) {
            return $this->json(['success' => false, 'message' => 'unavailable']);
        }

        $isWishlisted = $this->wishlistManager->toggle($product);

        return $this->json([
            'success' => true,
            'isWishlisted' => $isWishlisted,
            'count' => $this->wishlistManager->count(),
        ]);
    }

    #[Route(
        path: ['en' => '/account/wishlist', 'fr' => '/mon-compte/mes-envies'],
        name: 'shop_account_wishlist',
    )]
    #[IsGranted('ROLE_CUSTOMER')]
    public function wishlist(): Response
    {
        /** @var Customer $customer */
        $customer = $this->getUser();

        $removedCount = $this->wishlistManager->cleanSoldItems();

        if ($removedCount > 0) {
            $this->addFlash('info', $this->translator->trans('wishlist.items_sold'));
        }

        $items = $this->wishlistManager->getVisibleItems();

        $reservedProductIds = $this->reservationManager->getReservedProductIdsByOthers();

        return $this->render('shop/account/wishlist.html.twig', [
            'customer' => $customer,
            'items' => $items,
            'reservedProductIds' => $reservedProductIds,
        ]);
    }
}
