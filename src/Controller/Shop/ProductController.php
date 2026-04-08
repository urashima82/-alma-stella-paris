<?php

declare(strict_types=1);

namespace App\Controller\Shop;

use App\Entity\Product;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class ProductController extends AbstractController
{
    #[Route('/product/{slug}', name: 'shop_product')]
    public function show(#[MapEntity(mapping: ['slug' => 'slug'])] Product $product): Response
    {
        if (!$product->isVisibleInCatalog()) {
            throw $this->createNotFoundException('Product not found.');
        }

        return $this->render('shop/product/show.html.twig', [
            'product' => $product,
        ]);
    }
}
