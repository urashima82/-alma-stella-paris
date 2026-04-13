<?php

declare(strict_types=1);

namespace App\Controller\Shop;

use App\Repository\ProductCategoryRepository;
use App\Repository\ProductRepository;
use App\Repository\SiteSettingsRepository;
use App\Service\ReservationManager;
use Knp\Component\Pager\PaginatorInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class CatalogController extends AbstractController
{
    #[Route(
        path: ['en' => '/shop/{categorySlug}', 'fr' => '/boutique/{categorySlug}'],
        name: 'shop_catalog',
        defaults: ['categorySlug' => null],
    )]
    public function index(
        Request $request,
        ProductRepository $productRepository,
        ProductCategoryRepository $categoryRepository,
        SiteSettingsRepository $siteSettingsRepository,
        PaginatorInterface $paginator,
        ReservationManager $reservationManager,
        ?string $categorySlug = null,
    ): Response {
        $categories = $categoryRepository->findAllOrdered();
        $activeCategory = null;

        if (null !== $categorySlug) {
            $slugField = 'fr' === $request->getLocale() ? 'slugFr' : 'slug';
            $activeCategory = $categoryRepository->findOneBy([$slugField => $categorySlug]);

            if (null === $activeCategory) {
                throw $this->createNotFoundException('Category not found.');
            }
        }

        $activeCollection = $siteSettingsRepository->getActiveCollection();
        $query = $productRepository->findVisibleQuery($activeCategory, $activeCollection);

        $pagination = $paginator->paginate(
            $query,
            $request->query->getInt('page', 1),
            12,
        );

        return $this->render('shop/catalog/index.html.twig', [
            'categories' => $categories,
            'activeCategory' => $activeCategory,
            'pagination' => $pagination,
            'reservedProductIds' => $reservationManager->getReservedProductIdsByOthers(),
        ]);
    }
}
