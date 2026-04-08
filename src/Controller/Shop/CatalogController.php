<?php

declare(strict_types=1);

namespace App\Controller\Shop;

use App\Repository\ProductCategoryRepository;
use App\Repository\ProductRepository;
use Knp\Component\Pager\PaginatorInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class CatalogController extends AbstractController
{
    #[Route('/shop/{categorySlug?}', name: 'shop_catalog')]
    public function index(
        Request $request,
        ProductRepository $productRepository,
        ProductCategoryRepository $categoryRepository,
        PaginatorInterface $paginator,
        ?string $categorySlug = null,
    ): Response {
        $categories = $categoryRepository->findAllOrdered();
        $activeCategory = null;

        if (null !== $categorySlug) {
            $activeCategory = $categoryRepository->findOneBy(['slug' => $categorySlug]);

            if (null === $activeCategory) {
                throw $this->createNotFoundException('Category not found.');
            }
        }

        $query = $productRepository->findVisibleQuery($activeCategory);

        $pagination = $paginator->paginate(
            $query,
            $request->query->getInt('page', 1),
            12,
        );

        return $this->render('shop/catalog/index.html.twig', [
            'categories' => $categories,
            'activeCategory' => $activeCategory,
            'pagination' => $pagination,
        ]);
    }
}
