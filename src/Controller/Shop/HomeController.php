<?php

declare(strict_types=1);

namespace App\Controller\Shop;

use App\Repository\ProductRepository;
use App\Repository\SiteSettingsRepository;
use App\Repository\TestimonialRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class HomeController extends AbstractController
{
    #[Route('/', name: 'shop_home')]
    public function index(
        ProductRepository $productRepository,
        SiteSettingsRepository $siteSettingsRepository,
        TestimonialRepository $testimonialRepository,
    ): Response {
        $activeCollection = $siteSettingsRepository->getActiveCollection();

        return $this->render('shop/home/index.html.twig', [
            'featuredProducts' => $productRepository->findFeatured(4, $activeCollection),
            'testimonials' => $testimonialRepository->findApproved(3),
            'testimonialStats' => $testimonialRepository->getApprovedStats(),
        ]);
    }
}
