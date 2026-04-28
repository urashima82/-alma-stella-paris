<?php

declare(strict_types=1);

namespace App\Controller\Shop;

use App\Repository\ProductCategoryRepository;
use App\Repository\ProductRepository;
use App\Repository\SiteSettingsRepository;
use App\Repository\StoneRepository;
use App\Service\ReservationManager;
use Knp\Component\Pager\PaginatorInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class CatalogController extends AbstractController
{
    #[Route(
        path: ['en' => '/shop/{parentSlug}/{childSlug}', 'fr' => '/boutique/{parentSlug}/{childSlug}'],
        name: 'shop_catalog',
        defaults: ['parentSlug' => null, 'childSlug' => null],
        requirements: ['parentSlug' => '[a-z0-9-]+', 'childSlug' => '[a-z0-9-]+'],
    )]
    public function index(
        Request $request,
        ProductRepository $productRepository,
        ProductCategoryRepository $categoryRepository,
        SiteSettingsRepository $siteSettingsRepository,
        StoneRepository $stoneRepository,
        PaginatorInterface $paginator,
        ReservationManager $reservationManager,
        ?string $parentSlug = null,
        ?string $childSlug = null,
    ): Response {
        $rootCategories = $categoryRepository->findRootCategories();
        $locale = $request->getLocale();
        $activeCategory = null;
        $activeParent = null;

        if ($parentSlug !== null) {
            $activeParent = $categoryRepository->findBySlug($parentSlug, $locale);

            if ($activeParent === null || !$activeParent->isRoot()) {
                throw $this->createNotFoundException('Category not found.');
            }

            if ($childSlug !== null) {
                $activeCategory = $categoryRepository->findBySlug($childSlug, $locale);

                if ($activeCategory === null || $activeCategory->getParent()?->getId() !== $activeParent->getId()) {
                    throw $this->createNotFoundException('Subcategory not found.');
                }
            } else {
                $activeCategory = $activeParent;
            }
        }

        $activeCollection = $siteSettingsRepository->getActiveCollection();

        $stonesParam = $request->query->getString('stones');
        $noStoneFilter = $stonesParam === 'none';
        $stoneSlugs = [];
        $activeStones = [];

        if ($stonesParam !== '' && !$noStoneFilter) {
            $stoneSlugs = \array_values(\array_filter(\array_map('trim', \explode(',', $stonesParam))));
            $activeStones = $stoneRepository->findBySlugs($stoneSlugs, $locale);
        }

        $query = $productRepository->findVisibleQuery($activeCategory, $activeCollection, $activeStones, $noStoneFilter);

        $pagination = $paginator->paginate(
            $query,
            $request->query->getInt('page', 1),
            12,
        );

        $totalProductCount = $productRepository->findVisibleQuery(null, $activeCollection)
            ->select('COUNT(p.id)')
            ->getQuery()
            ->getSingleScalarResult();

        return $this->render('shop/catalog/index.html.twig', [
            'rootCategories' => $rootCategories,
            'activeCategory' => $activeCategory,
            'activeParent' => $activeParent,
            'pagination' => $pagination,
            'totalProductCount' => (int) $totalProductCount,
            'reservedProductIds' => $reservationManager->getReservedProductIdsByOthers(),
            'stones' => $stoneRepository->findAllOrdered(),
            'activeStones' => $activeStones,
            'noStoneFilter' => $noStoneFilter,
        ]);
    }
}
