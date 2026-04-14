<?php

declare(strict_types=1);

namespace App\Controller\Shop;

use App\Repository\ProductRepository;
use App\Service\CartManager;
use App\Service\ReservationManager;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class ProductController extends AbstractController
{
    #[Route(
        path: ['en' => '/product/{slug}', 'fr' => '/produit/{slug}'],
        name: 'shop_product',
    )]
    public function show(
        string $slug,
        Request $request,
        ProductRepository $productRepository,
        CartManager $cartManager,
        ReservationManager $reservationManager,
    ): Response {
        $slugField = $request->getLocale() === 'fr' ? 'slugFr' : 'slug';
        $product = $productRepository->findOneBy([$slugField => $slug]);

        if ($product === null || !$product->isVisibleInCatalog()) {
            throw $this->createNotFoundException('Product not found.');
        }

        return $this->render('shop/product/show.html.twig', [
            'product' => $product,
            'isInCart' => $product->getId() !== null && $cartManager->contains($product->getId()),
            'isReserved' => $reservationManager->isReservedByOther($product),
        ]);
    }
}
