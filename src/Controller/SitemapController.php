<?php

declare(strict_types=1);

namespace App\Controller;

use App\Repository\ProductCategoryRepository;
use App\Repository\ProductRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class SitemapController extends AbstractController
{
    #[Route('/sitemap.xml', name: 'sitemap', defaults: ['_format' => 'xml'])]
    public function index(
        ProductRepository $productRepository,
        ProductCategoryRepository $categoryRepository,
    ): Response {
        $response = $this->render('sitemap.xml.twig', [
            'products' => $productRepository->findPublished(),
            'categories' => $categoryRepository->findAllOrdered(),
        ]);

        $response->headers->set('Content-Type', 'application/xml');

        return $response;
    }
}
